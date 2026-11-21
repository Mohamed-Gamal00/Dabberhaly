<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Repositories\Order\OrderRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserOrdersController extends Controller
{
    protected $orderRepository;

    public function __construct(OrderRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function cancel(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $user  = Auth::guard('web')->user();
        $order = Order::where('id', $request->order_id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Delivered / already-cancelled / already-returned orders
        // cannot be cancelled (see OrderRepository::cancel).
        $cancelled = $this->orderRepository->cancel($order->id);

        if (! $cancelled) {
            return response()->json(['message' => __('flash.order_cancel_not_allowed')], 422);
        }

        return response()->json(['message' => __('flash.order_cancel_success')]);
    }
}
