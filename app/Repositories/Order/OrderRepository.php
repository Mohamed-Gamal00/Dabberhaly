<?php

namespace App\Repositories\Order;

use App\Enums\OrderStatus;
use App\Helper\Helper;
use App\Models\Order;
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

        $status = $request->status instanceof OrderStatus
            ? $request->status->value
            : $request->status;

        $data = [
            'status' => $status,
        ];

        // Once the order is delivered, the payment is considered collected,
        // so the order is marked as paid as well.
        if ($status === OrderStatus::Delivered->value) {
            $data['payment_status'] = 'paid';
        }

        $order->update($data);

        return $order;
    }

    public function delete($id)
    {
        $order = $this->order->findOrFail($id);
        $order->delete();

        return $order;
    }
}
