<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller

{
    public function index($order_number)
    {
        $order = Order::where('number', $order_number)->first();
        $publishable_key = Setting::all()->pluck('publishable_key')->first(); // This will get the first 'publishable_key' if present
        if ($order) {
            if ($order->payment_status == 'paid') {
                // Flash message to session
                session()->flash('success', 'This order has already been paid.');
            }

            // Exact amount Moyasar must charge (halalas), computed
            // server-side so the form cannot over- or under-charge.
            $amountHalalas = $order->expectedPaymentAmountHalalas();

            return view('front.user-payment', compact('order', 'publishable_key', 'amountHalalas'));
        } else {
            return "Order does not exist.";
        }
    }
    public function callback($number)
    {
        $order = Order::where('number', $number)->first();

        if (!$order) {
            return redirect()->route('home')->with('error', 'Order not found.');
        }

        if ($order->payment_status == 'paid') {
            return redirect()->route('user.payment', [$order->number])->with('success', 'This order has already been paid');
        }

        $id = request()->query('id');
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
                    return response()->json(['status' => 'success', 'message' => 'Payment faild', 'order_number' => $order->number], 200);
                } elseif ($order->cookie_id != null) {
                    return redirect()->route('home')->with('success', 'فشل عملية الدفع');
                } else {
                    return response()->json(['status' => 'success', 'message' => 'no action', 'order_number' => $order->number], 200);
                }
            }
        } else {
            return response()->json(['status' => 'success', 'message' => 'Payment failed', 'order_number' => $order->number], 200);
        }
    }
}
