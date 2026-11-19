<?php

namespace App\Repositories\Order;

use App\Enums\OrderStatus;
use App\Helper\Helper;
use App\Models\Order;
use App\Models\Product;
use App\Repositories\Order\OrderInterface;

class OrderRepository implements OrderInterface
{
    use Helper;

    protected $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function getAll()
    {
        return $this->order->latest()
            ->with('user', 'guest', 'addresses', 'addresses.country', 'addresses.city', 'orderItems')
            ->filter(request()->input('order_number'))
            ->where('return_order', false)
            ->paginate();
    }

    public function show($id)
    {
        return $this->order->with('user', 'guest', 'discountCode', 'choices', 'addresses', 'addresses.country', 'addresses.city', 'orderItems.product', 'orderItems')
            ->where('return_order', false)
            ->findOrFail($id);
    }

    public function update($request, $id)
    {
        $order = $this->order->findOrFail($id);

        // The dashboard sends the status as a string ('delivered'); it may
        // also arrive as an OrderStatus enum. Normalize it first so the
        // delivered check below works either way.
        $status = $request->status instanceof OrderStatus
            ? $request->status
            : OrderStatus::tryFrom((string) $request->status);

        $data = ['status' => $status ?? $request->status];

        // A delivered order is paid ONLY when the customer pays cash on
        // delivery: a card order that was never paid must never be
        // marked paid just because it was delivered.
        if ($status === OrderStatus::Delivered && $order->isCashPayment()) {
            $data['payment_status'] = 'paid';
        }

        $wasCancelled = $order->status === OrderStatus::Cancelled;

        $order->update($data);

        // Cancelling returns the items to stock. payment_status is left
        // untouched on purpose: refunds are manual, via the Moyasar
        // dashboard.
        if ($status === OrderStatus::Cancelled && ! $wasCancelled) {
            foreach ($order->orderItems as $orderItem) {
                Product::where('id', $orderItem->product_id)
                    ->increment('quantity', $orderItem->quantity);
            }
        }

        return $order;
    }

    public function delete($id)
    {
        $order = $this->order->findOrFail($id);
        $order->delete();

        return $order;
    }
}
