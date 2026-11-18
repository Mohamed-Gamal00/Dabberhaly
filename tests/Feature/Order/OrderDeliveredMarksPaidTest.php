<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Repositories\Order\OrderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class OrderDeliveredMarksPaidTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_status_to_delivered_marks_order_as_paid(): void
    {
        $order = Order::create([
            'status' => OrderStatus::Confirmed->value,
            'payment_status' => 'unpaid',
            'payment_method' => 'cash',
        ]);

        $repository = new OrderRepository(new Order);
        $repository->update(new Request(['status' => 'delivered']), $order->id);

        $order = $order->fresh();

        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertSame('paid', $order->payment_status);
    }

    public function test_updating_status_to_other_values_does_not_change_payment_status(): void
    {
        $order = Order::create([
            'status' => OrderStatus::Confirmed->value,
            'payment_status' => 'unpaid',
            'payment_method' => 'cash',
        ]);

        $repository = new OrderRepository(new Order);
        $repository->update(new Request(['status' => 'shipped']), $order->id);

        $order = $order->fresh();

        $this->assertSame(OrderStatus::Shipped, $order->status);
        $this->assertSame('unpaid', $order->payment_status);
    }
}
