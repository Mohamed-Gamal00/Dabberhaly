<?php

namespace App\Http\Controllers\Inertia;

use App\Enums\OrderStatus;
use App\Events\OrderReturned;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReturnProductsController extends Controller
{
    public function index()
    {
        $user = Auth::guard('web')->user();

        $returnOrders = $user->orders()
            ->with('orderItems')
            ->where('return_order', true)
            ->latest()
            ->get();

        return response()->json($returnOrders);
    }

    public function store(Request $request)
    {
        $request->validate([
            'return_order_id' => 'required|exists:orders,id',
        ]);

        $user  = Auth::guard('web')->user();
        $order = Order::where('id', $request->return_order_id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Already returned (e.g. double click): succeed quietly, no second alert.
        if ($order->return_order) {
            return response()->json(['message' => __('flash.return_request_success')]);
        }

        // Only a pending order can be returned — once it moves on, it is
        // already being prepared.
        if (! $order->canBeReturned()) {
            return response()->json(['message' => __('flash.return_not_allowed')], 422);
        }

        $order->update([
            'return_order' => true,
            'status'       => OrderStatus::Pending,
        ]);

        event(new OrderReturned($order));

        return response()->json(['message' => __('flash.return_request_success')]);
    }
}
