<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Guest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCancelTest extends TestCase
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

    private function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['password' => 'password'], $overrides));
    }

    /** An order with one item of quantity 2, on a product with stock 10. */
    private function makeOrder(array $overrides = []): array
    {
        $product = Product::factory()->stock(10)->create();

        $order = Order::create(array_merge([
            'payment_method' => 'cash',
            'status' => OrderStatus::Pending,
            'total_price' => 100,
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 100,
            'quantity' => 2,
        ]);

        return [$order->fresh(), $product->fresh()];
    }

    // --- Admin delete restores stock ---

    public function test_admin_delete_restores_stock(): void
    {
        [$order, $product] = $this->makeOrder();

        $this->actingAs($this->admin(), 'admin')
            ->delete("/admin/orders/{$order->id}/delete")
            ->assertRedirect();

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
        $this->assertSame(12, $product->fresh()->quantity);
    }

    public function test_admin_delete_of_an_already_cancelled_order_does_not_restore_stock_twice(): void
    {
        [$order, $product] = $this->makeOrder();
        $admin = $this->admin();

        // Cancel first via the admin status update (restores stock once).
        $this->actingAs($admin, 'admin')
            ->put("/admin/orders/{$order->id}/update", ['status' => 'cancelled'])
            ->assertRedirect(route('orders.index'));
        $this->assertSame(12, $product->fresh()->quantity);

        // Deleting the cancelled order must not restore stock again.
        $this->actingAs($admin, 'admin')
            ->delete("/admin/orders/{$order->id}/delete")
            ->assertRedirect();

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
        $this->assertSame(12, $product->fresh()->quantity);
    }

    // --- API (logged-in user) cancel ---

    public function test_api_user_can_cancel_a_pending_order_and_stock_returns(): void
    {
        $user = $this->makeUser();
        [$order, $product] = $this->makeOrder(['user_id' => $user->id]);

        $this->actingAs($user, 'user')
            ->postJson('/api/v1/user-orders/cancel', ['order_number' => $order->number])
            ->assertStatus(200);

        $this->assertEquals(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(12, $product->fresh()->quantity);
    }

    public function test_api_user_cannot_cancel_a_delivered_order_and_stock_is_untouched(): void
    {
        $user = $this->makeUser();
        [$order, $product] = $this->makeOrder([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
        ]);

        $this->actingAs($user, 'user')
            ->postJson('/api/v1/user-orders/cancel', ['order_number' => $order->number])
            ->assertStatus(422);

        $this->assertEquals(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_api_user_cannot_cancel_another_users_order(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser(['email' => 'other@example.com', 'phone_number' => '01111111111']);
        [$order, $product] = $this->makeOrder(['user_id' => $other->id]);

        $this->actingAs($user, 'user')
            ->postJson('/api/v1/user-orders/cancel', ['order_number' => $order->number])
            ->assertStatus(404);

        $this->assertEquals(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_api_user_cancelling_an_already_cancelled_order_is_idempotent(): void
    {
        $user = $this->makeUser();
        [$order, $product] = $this->makeOrder([
            'user_id' => $user->id,
            'status' => OrderStatus::Cancelled,
        ]);

        // Same-status is an allowed transition (canTransitionTo), so
        // cancelling twice succeeds quietly — like the return flow —
        // and the already-cancelled guard in update() means stock is
        // NOT restored a second time.
        $this->actingAs($user, 'user')
            ->postJson('/api/v1/user-orders/cancel', ['order_number' => $order->number])
            ->assertStatus(200);

        $this->assertEquals(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->quantity);
    }

    // --- API (guest) cancel ---

    public function test_api_guest_can_cancel_own_pending_order(): void
    {
        $guest = Guest::create(['cookie_id' => 'guest-cookie-1']);
        [$order, $product] = $this->makeOrder(['guest_id' => $guest->id]);

        $this->withHeaders(['guest-id' => 'guest-cookie-1'])
            ->postJson('/api/v1/guest-order/cancel', ['order_number' => $order->number])
            ->assertStatus(200);

        $this->assertEquals(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(12, $product->fresh()->quantity);
    }

    public function test_api_guest_cannot_cancel_a_delivered_order(): void
    {
        $guest = Guest::create(['cookie_id' => 'guest-cookie-1']);
        [$order, $product] = $this->makeOrder([
            'guest_id' => $guest->id,
            'status' => OrderStatus::Delivered,
        ]);

        $this->withHeaders(['guest-id' => 'guest-cookie-1'])
            ->postJson('/api/v1/guest-order/cancel', ['order_number' => $order->number])
            ->assertStatus(422);

        $this->assertEquals(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->quantity);
    }

    // --- Web (logged-in user) cancel ---

    public function test_web_user_can_cancel_a_pending_order(): void
    {
        $user = $this->makeUser();
        [$order, $product] = $this->makeOrder(['user_id' => $user->id]);

        $this->actingAs($user, 'web')
            ->post(route('user.orders.cancel'), ['order_id' => $order->id])
            ->assertOk();

        $this->assertEquals(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(12, $product->fresh()->quantity);
    }

    public function test_web_user_cannot_cancel_a_delivered_order(): void
    {
        $user = $this->makeUser();
        [$order, $product] = $this->makeOrder([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
        ]);

        $this->actingAs($user, 'web')
            ->post(route('user.orders.cancel'), ['order_id' => $order->id])
            ->assertStatus(422);

        $this->assertEquals(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->quantity);
    }
}
