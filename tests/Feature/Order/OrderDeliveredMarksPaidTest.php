<?php

namespace Tests\Feature\Order;

use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDeliveredMarksPaidTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        // forceCreate: super_admin must be set even if another change
        // removes it from $fillable — the Gate bypass depends on it.
        return Admin::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'super_admin' => true,
        ]);
    }

    public function test_updating_a_cash_on_delivery_order_to_delivered_marks_it_as_paid(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create(['password' => 'password']);
        $order = Order::create([
            'user_id' => $user->id,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('orders.update', $order->id), ['status' => 'delivered'])
            ->assertRedirect(route('orders.index'));

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_updating_a_card_order_to_delivered_leaves_it_unpaid(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create(['password' => 'password']);
        $order = Order::create([
            'user_id' => $user->id,
            'payment_method' => 'card_payment',
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('orders.update', $order->id), ['status' => 'delivered'])
            ->assertRedirect(route('orders.index'));

        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_cancelling_an_order_restores_stock(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create(['password' => 'password']);
        $product = \App\Models\Product::factory()->create(['quantity' => 3]);
        $order = Order::create([
            'user_id' => $user->id,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'unpaid',
        ]);
        \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 100,
            'quantity' => 2,
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('orders.update', $order->id), ['status' => 'cancelled'])
            ->assertRedirect(route('orders.index'));

        $this->assertSame(5, $product->fresh()->quantity);
        // Refunds stay manual: payment_status untouched.
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_updating_an_order_to_another_status_leaves_payment_status_untouched(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create(['password' => 'password']);
        $order = Order::create([
            'user_id' => $user->id,
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('orders.update', $order->id), ['status' => 'shipped'])
            ->assertRedirect(route('orders.index'));

        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }
}
