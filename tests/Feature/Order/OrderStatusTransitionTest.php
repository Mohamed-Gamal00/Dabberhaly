<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::forceCreate([
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'password' => 'password',
            'super_admin' => true,
        ]);
    }

    private function makeOrder(OrderStatus $status): Order
    {
        $user = User::factory()->create(['password' => 'password']);

        return Order::create([
            'user_id' => $user->id,
            'payment_method' => 'cash',
            'status' => $status,
            'total_price' => 100,
        ]);
    }

    public function test_forward_transition_is_allowed(): void
    {
        $order = $this->makeOrder(OrderStatus::Pending);

        $this->actingAs($this->admin(), 'admin')
            ->put("/admin/orders/{$order->id}/update", ['status' => 'confirmed'])
            ->assertRedirect(route('orders.index'));

        $this->assertEquals(OrderStatus::Confirmed, $order->fresh()->status);
    }

    public function test_delivered_order_cannot_be_cancelled(): void
    {
        $order = $this->makeOrder(OrderStatus::Delivered);

        $this->actingAs($this->admin(), 'admin')
            ->put("/admin/orders/{$order->id}/update", ['status' => 'cancelled'])
            ->assertRedirect();

        $this->assertEquals(OrderStatus::Delivered, $order->fresh()->status);
    }

    public function test_cancelled_order_cannot_move_again(): void
    {
        $order = $this->makeOrder(OrderStatus::Cancelled);

        $this->actingAs($this->admin(), 'admin')
            ->put("/admin/orders/{$order->id}/update", ['status' => 'pending'])
            ->assertRedirect();

        $this->assertEquals(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_pending_order_may_jump_forward(): void
    {
        $order = $this->makeOrder(OrderStatus::Pending);

        $this->actingAs($this->admin(), 'admin')
            ->put("/admin/orders/{$order->id}/update", ['status' => 'shipped'])
            ->assertRedirect(route('orders.index'));

        $this->assertEquals(OrderStatus::Shipped, $order->fresh()->status);
    }

    public function test_backwards_transition_is_blocked(): void
    {
        $order = $this->makeOrder(OrderStatus::Shipped);

        $this->actingAs($this->admin(), 'admin')
            ->put("/admin/orders/{$order->id}/update", ['status' => 'confirmed'])
            ->assertRedirect();

        $this->assertEquals(OrderStatus::Shipped, $order->fresh()->status);
    }
}
