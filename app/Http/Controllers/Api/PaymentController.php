<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller

{
    /**
     * Does the caller own this order? A user order needs the matching
     * authenticated API user; a guest order needs the guest-id header
     * matching the cookie the order was placed with.
     */
    protected function ownsOrder(Request $request, Order $order): bool
    {
        if ($order->user_id) {
            // The payment page route has no auth middleware, so
            // resolve the API user guard explicitly.
            $user = $request->user('user');

            return $user !== null && (int) $user->id === (int) $order->user_id;
        }

        $guestId = $request->header('guest-id');

        return $guestId !== null
            && ($guestId === $order->cookie_id
                || $guestId === $order->guest?->cookie_id);
    }

    public function index(Request $request, $order_number)
    {
        $order = Order::where('number', $order_number)->first();
        $publishable_key = Setting::all()->pluck('publishable_key')->first(); // This will get the first 'publishable_key' if present
        if ($order) {
            // A bare order number is not enough: only the owner may
            // open another customer's payment page.
            if (! $this->ownsOrder($request, $order)) {
                return response()->json(['message' => 'Order not found.'], 404);
            }

            if ($order->payment_status == 'paid') {
                // Flash message to session
                session()->flash('success', 'This order has already been paid.');
            }

            // Exact amount Moyasar must charge (halalas), computed
            // server-side so the form cannot over- or under-charge.
            $amountHalalas = $order->expectedPaymentAmountHalalas();

            return view('front.user-payment', compact('order', 'publishable_key', 'amountHalalas'));
        } else {
            return response()->json(['message' => 'Order not found.'], 404);
        }
    }
    public function callback($number)
    {
        $order = Order::where('number', $number)->first();

        if (!$order) {
            return redirect()->route('home')->with('error', 'Order not found.');
        }

        // Idempotency: an already-paid order is never re-processed.
        if ($order->payment_status == 'paid') {
            if (Auth::guard('web')->check()) {
                return redirect()->route('user.payment', [$order->number])->with('success', 'This order has already been paid');
            }

            return response()->json(['status' => 'success', 'message' => 'Payment successful', 'order_number' => $order->number], 200);
        }

        // A different logged-in web user must not open another
        // customer's payment result.
        if (Auth::guard('web')->check() && $order->user_id
            && (int) Auth::guard('web')->id() !== (int) $order->user_id) {
            return response()->json(['status' => 'error', 'message' => 'Order not found.'], 404);
        }

        $id = request()->query('id');

        // No payment id: Moyasar never confirmed anything, so the
        // order stays unpaid (it must NOT be marked failed — the
        // payment may still have succeeded and the webhook can pick
        // it up). 422, never 200, so callers do not mistake it for a
        // completed payment.
        if (! $id) {
            return response()->json(['status' => 'error', 'message' => 'Payment failed', 'order_number' => $order->number], 422);
        }

        $secret_key = Setting::all()->pluck('secret_key')->first();
        $token = base64_encode($secret_key . ':');

        $payment = Http::baseUrl('https://api.moyasar.com/v1')
            ->withHeaders([
                'Authorization' => "Basic {$token}",
            ])
            ->get("payments/{$id}")
            ->json();

        if (! is_array($payment)) {
            $payment = [];
        }

        // Check if the response contains an authentication error
        if (isset($payment['type']) && $payment['type'] === 'authentication_error') {
            return redirect()->route('user.payment', [$number])
                ->with('danger', __('general.Invalid_authorization_credentials'));
        }

        // A payment only counts when Moyasar confirms it for THIS order:
        // right amount (halalas), right currency, and the order number
        // in the description. Without this, a payment made for a cheap
        // order could be replayed against an expensive one.
        $verifiedPaid = ($payment['status'] ?? null) === 'paid'
            && isset($payment['amount'])
            && (int) $payment['amount'] === $order->expectedPaymentAmountHalalas()
            && ($payment['currency'] ?? null) === 'SAR'
            && str_contains((string) ($payment['description'] ?? ''), (string) $order->number);

        $status = $payment['status'] ?? 'failed';

        // Record the transaction, like the web path does — but only
        // once per Moyasar payment id (callbacks and webhooks may both
        // fire for the same payment).
        if (! $order->transactions()->where('moyasar_payment_id', $payment['id'] ?? null)->exists()) {
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
                'ip_address'         => request()->ip(),
            ]);
        }

        if ($verifiedPaid) {
            $order->payment_status = 'paid';
            $order->save();

            if (Auth::guard('web')->check()) {
                return redirect()->route('home')->with('success', 'عملية دفع ناجحة');
            } else {
                if ($order->guest_id != null || $order->user_id != null) {
                    return response()->json(['status' => 'success', 'message' => 'Payment successful', 'order_number' => $order->number], 200);
                } elseif ($order->cookie_id != null) {
                    return redirect()->route('home')->with('success', 'عملية دفع ناجحة');
                } else {
                    return response()->json(['status' => 'success', 'message' => 'no action', 'order_number' => $order->number], 200);
                }
            }
        } elseif (($payment['status'] ?? null) === 'failed' || ($payment['status'] ?? null) === 'paid') {
            // 'failed' from Moyasar, or 'paid' that failed verification
            // (wrong amount / currency / order) — never mark it paid.
            $order->payment_status = 'failed';
            $order->save();
            if (Auth::guard('web')->check()) {
                return redirect()->route('home')->with('success', 'فشل عملية الدفع');
            } else {
                if ($order->guest_id != null || $order->user_id != null) {
                    return response()->json(['status' => 'error', 'message' => 'Payment failed', 'order_number' => $order->number], 422);
                } elseif ($order->cookie_id != null) {
                    return redirect()->route('home')->with('success', 'فشل عملية الدفع');
                } else {
                    return response()->json(['status' => 'error', 'message' => 'no action', 'order_number' => $order->number], 422);
                }
            }
        } else {
            return response()->json(['status' => 'error', 'message' => 'Payment failed', 'order_number' => $order->number], 422);
        }
    }
}
