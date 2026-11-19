<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Moyasar payment webhook.
 *
 * Moyasar calls this when a payment changes state, so an order is
 * still marked paid even if the customer closed the tab before the
 * redirect callback ran.
 *
 * The payload itself is NEVER trusted: the payment is always fetched
 * again from Moyasar with the secret key and verified (status, exact
 * amount in halalas, SAR, and this order's number in the description)
 * before anything is marked paid.
 *
 * NOTE: register this URL in the Moyasar dashboard
 * (Settings → Webhooks) or Moyasar will never call it:
 * POST {APP_URL}/api/v1/webhooks/moyasar
 */
class MoyasarWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->json()->all();

        // Moyasar sends either the payment object directly or wrapped
        // in a `data` key, depending on the event format.
        $payment = $payload['data'] ?? $payload;
        $paymentId = is_array($payment) ? ($payment['id'] ?? null) : null;

        // No payment id: there is no order to act on. Never fail an
        // order we cannot identify — acknowledge so Moyasar stops
        // retrying a payload that can never resolve.
        if (! $paymentId) {
            Log::info('Moyasar webhook ignored: no payment id in payload.');
            return response()->json(['status' => 'ignored', 'message' => 'No payment id in payload.'], 200);
        }

        // Verify with Moyasar — never trust the payload alone.
        $secretKey = Setting::first()?->secret_key;
        $token = base64_encode($secretKey . ':');

        try {
            $response = Http::baseUrl('https://api.moyasar.com/v1')
                ->withHeaders(['Authorization' => "Basic {$token}"])
                ->get("payments/{$paymentId}");
        } catch (\Throwable $e) {
            Log::error('Moyasar webhook verification failed', ['exception' => $e]);

            return response()->json(['status' => 'error', 'message' => 'Could not verify payment.'], 502);
        }

        $verified = $response->json();

        if (! $response->successful() || ! is_array($verified)) {
            Log::warning('Moyasar webhook: payment lookup failed.', [
                'payment_id' => $paymentId,
                'http_status' => $response->status(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Could not verify payment.'], 502);
        }

        // The order number travels in the payment description
        // ("Order number 2026000001").
        $description = (string) ($verified['description'] ?? '');
        if (! preg_match('/(\d{6,})/', $description, $matches)) {
            Log::info('Moyasar webhook ignored: no order number in payment description.', [
                'payment_id' => $paymentId,
            ]);

            return response()->json(['status' => 'ignored', 'message' => 'No order number in payment description.'], 200);
        }

        $order = Order::where('number', $matches[1])->first();

        if (! $order) {
            Log::info('Moyasar webhook ignored: order not found.', [
                'payment_id' => $paymentId,
                'order_number' => $matches[1],
            ]);

            return response()->json(['status' => 'ignored', 'message' => 'Order not found.'], 200);
        }

        // Idempotency: an already-paid order is never re-processed,
        // and a payment id is never recorded twice (the redirect
        // callback may have handled the same payment already).
        if ($order->payment_status === 'paid') {
            return response()->json(['status' => 'success', 'message' => 'Order already paid.'], 200);
        }

        $verifiedPaid = ($verified['status'] ?? null) === 'paid'
            && isset($verified['amount'])
            && (int) $verified['amount'] === $order->expectedPaymentAmountHalalas()
            && ($verified['currency'] ?? null) === 'SAR'
            && str_contains($description, (string) $order->number);

        $status = $verified['status'] ?? 'failed';

        if (! $order->transactions()->where('moyasar_payment_id', $paymentId)->exists()) {
            $source = $verified['source'] ?? [];
            PaymentTransaction::create([
                'order_id'           => $order->id,
                'moyasar_payment_id' => $paymentId,
                'status'             => $status,
                'amount'             => ($verified['amount'] ?? 0) / 100,
                'currency'           => $verified['currency'] ?? 'SAR',
                'payment_method'     => $source['type'] ?? null,
                'card_brand'         => $source['brand'] ?? null,
                'card_last_four'     => isset($source['number']) ? substr($source['number'], -4) : null,
                'description'        => $description ?: null,
                'raw_response'       => json_encode($verified),
                'ip_address'         => $request->ip(),
            ]);
        }

        if ($verifiedPaid) {
            $order->update(['payment_status' => 'paid']);

            return response()->json(['status' => 'success', 'message' => 'Payment successful', 'order_number' => $order->number], 200);
        }

        if ($status === 'failed') {
            $order->update(['payment_status' => 'failed']);
        }

        return response()->json(['status' => 'success', 'message' => 'Payment not verified as paid.', 'order_number' => $order->number], 200);
    }
}
