<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class PaymentController extends Controller
{
    /**
     * Show the Moyasar payment form for a given order number ..
     *
     * A bare order number is not enough: the page only serves the
     * order owner — the logged-in user for a user order, or the
     * browser holding the cart cookie the order was placed with for
     * a guest order.
     */
    public function show($order_number)
    {
        $order = Order::where('number', $order_number)
            ->with(['addresses', 'orderItems'])
            ->firstOrFail();

        if (! $this->ownsOrder($order)) {
            abort(404);
        }

        // Already paid — redirect to success
        if ($order->payment_status === 'paid') {
            return redirect()->route('home')
                ->with('success', 'هذا الطلب تم دفعه بالفعل.');
        }

        $setting        = Setting::first();
        $publishable_key = $setting?->publishable_key;

        $callbackUrl = url("/payment/{$order->number}/callback");

        return Inertia::render('Payment/Index', [
            'order'           => [
                'id'             => $order->id,
                'number'         => $order->number,
                'total_price'    => (float) $order->total_price,
                'shipping_price' => (float) ($order->shipping_price ?? 0),
                'payment_status' => $order->payment_status,
            ],
            'publishable_key' => $publishable_key,
            'callback_url'    => $callbackUrl,
            // The exact amount Moyasar must charge (halalas). The page
            // used to charge total_price + shipping_price, which
            // double-charged shipping for web orders (their total_price
            // already includes it).
            'amount_halalas'  => $order->expectedPaymentAmountHalalas(),
        ]);
    }

    /**
     * Handle Moyasar callback after payment attempt.
     */
    public function callback(Request $request, $order_number)
    {
        $order = Order::where('number', $order_number)
            ->with('orderItems')
            ->firstOrFail();

        // A different logged-in user must not open another customer's
        // payment result. (No identity at all — e.g. the Moyasar
        // redirect for a guest — still proceeds, because the payment
        // itself is verified against Moyasar below.)
        $user = Auth::guard('web')->user();
        if ($order->user_id && $user && (int) $user->id !== (int) $order->user_id) {
            abort(404);
        }

        // Already paid — go straight to success page
        if ($order->payment_status === 'paid') {
            return Inertia::render('Payment/Success', [
                'order' => $this->orderData($order),
            ]);
        }

        $paymentId  = $request->query('id');

        // No payment id: Moyasar never confirmed anything, so the
        // order stays unpaid (it must NOT be marked failed — the
        // payment may still have succeeded and the webhook can pick
        // it up). Show the failure page without changing the order.
        if (! $paymentId) {
            return Inertia::render('Payment/Failed', [
                'order'   => $this->orderData($order),
                'message' => 'تعذّر التحقق من عملية الدفع. يرجى التواصل مع الدعم.',
            ]);
        }

        $setting    = Setting::first();
        $secret_key = $setting?->secret_key;
        $token      = base64_encode($secret_key . ':');

        $payment = Http::baseUrl('https://api.moyasar.com/v1')
            ->withHeaders(['Authorization' => "Basic {$token}"])
            ->get("payments/{$paymentId}")
            ->json();

        if (! is_array($payment)) {
            $payment = [];
        }

        // Auth error
        if (isset($payment['type']) && $payment['type'] === 'authentication_error') {
            return Inertia::render('Payment/Failed', [
                'order'   => $this->orderData($order),
                'message' => 'بيانات بوابة الدفع غير صحيحة. يرجى التواصل مع الدعم.',
            ]);
        }

        $status = $payment['status'] ?? 'failed';

        // A payment only counts when Moyasar confirms it for THIS order:
        // right amount (halalas), right currency, and the order number
        // in the description. Without this, a payment made for a cheap
        // order could be replayed against an expensive one.
        $verifiedPaid = $status === 'paid'
            && isset($payment['amount'])
            && (int) $payment['amount'] === $order->expectedPaymentAmountHalalas()
            && ($payment['currency'] ?? null) === 'SAR'
            && str_contains((string) ($payment['description'] ?? ''), (string) $order->number);

        $order->update(['payment_status' => $verifiedPaid ? 'paid' : 'failed']);

        // ── Save transaction to DB ──
        $source = $payment['source'] ?? [];
        PaymentTransaction::create([
            'order_id'           => $order->id,
            'moyasar_payment_id' => $payment['id'] ?? null,
            'status'             => $status,
            'amount'             => ($payment['amount'] ?? 0) / 100,
            'currency'           => $payment['currency'] ?? 'SAR',
            'payment_method'     => $source['type'] ?? null,
            'card_brand'         => $source['brand'] ?? null,
            'card_last_four'     => isset($source['number']) ? substr($source['number'], -4) : null,
            'description'        => $payment['description'] ?? null,
            'raw_response'       => json_encode($payment),
            'ip_address'         => $request->ip(),
        ]);

        if ($status === 'paid' && ! $verifiedPaid) {
            return Inertia::render('Payment/Failed', [
                'order'   => $this->orderData($order),
                'message' => 'تعذّر التحقق من عملية الدفع. يرجى التواصل مع الدعم.',
            ]);
        }

        if ($verifiedPaid) {
            return Inertia::render('Payment/Success', [
                'order' => $this->orderData($order),
            ]);
        }

        return Inertia::render('Payment/Failed', [
            'order'   => $this->orderData($order),
            'message' => $payment['source']['message'] ?? 'فشلت عملية الدفع. يرجى المحاولة مرة أخرى.',
        ]);
    }

    private function orderData(Order $order): array
    {
        return [
            'number'         => $order->number,
            'total_price'    => (float) $order->total_price,
            'shipping_price' => (float) ($order->shipping_price ?? 0),
            'payment_status' => $order->payment_status,
            'items_count'    => $order->orderItems->sum('quantity'),
            'is_guest'       => is_null($order->user_id),
        ];
    }

    /**
     * Does the current browser own this order? Logged-in user orders
     * need the matching user; guest orders need the cart cookie the
     * order was placed with.
     */
    private function ownsOrder(Order $order): bool
    {
        $user = Auth::guard('web')->user();

        if ($order->user_id) {
            return $user !== null && (int) $user->id === (int) $order->user_id;
        }

        return $order->cookie_id !== null
            && $order->cookie_id === Cart::getCookieId();
    }
}
