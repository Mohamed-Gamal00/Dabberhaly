<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Models\Guest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiOrderItemDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(array $overrides = []): Order
    {
        $product = Product::factory()->create();

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
            'quantity' => 1,
        ]);

        return $order->fresh();
    }

    public function test_user_cannot_delete_item_from_another_users_order(): void
    {
        $owner = User::factory()->create(['password' => 'password']);
        $attacker = User::factory()->create(['password' => 'password']);
        $order = $this->makeOrder(['user_id' => $owner->id]);
        $productId = $order->orderItems->first()->product_id;

        $this->actingAs($attacker, 'user')
            ->postJson('/api/v1/user-orders/delete', [
                'product_id' => $productId,
                'order_number' => $order->number,
            ])
            ->assertStatus(404);

        $this->assertDatabaseHas('order_items', ['order_id' => $order->id]);
    }

    public function test_owner_can_delete_item_from_pending_order(): void
    {
        $owner = User::factory()->create(['password' => 'password']);
        $order = $this->makeOrder(['user_id' => $owner->id]);
        $productId = $order->orderItems->first()->product_id;

        $this->actingAs($owner, 'user')
            ->postJson('/api/v1/user-orders/delete', [
                'product_id' => $productId,
                'order_number' => $order->number,
            ])
            ->assertStatus(200);

        $this->assertDatabaseMissing('order_items', ['order_id' => $order->id]);
    }

    public function test_user_cannot_delete_item_from_confirmed_order(): void
    {
        $owner = User::factory()->create(['password' => 'password']);
        $order = $this->makeOrder([
            'user_id' => $owner->id,
            'status' => OrderStatus::Confirmed,
        ]);
        $productId = $order->orderItems->first()->product_id;

        $this->actingAs($owner, 'user')
            ->postJson('/api/v1/user-orders/delete', [
                'product_id' => $productId,
                'order_number' => $order->number,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('order_items', ['order_id' => $order->id]);
    }

    public function test_guest_cannot_delete_item_from_another_guests_order(): void
    {
        $guestA = Guest::create(['cookie_id' => 'guest-a-cookie']);
        $guestB = Guest::create(['cookie_id' => 'guest-b-cookie']);
        $order = $this->makeOrder(['guest_id' => $guestA->id]);
        $productId = $order->orderItems->first()->product_id;

        $this->withHeaders(['guest-id' => (string) $guestB->id])
            ->postJson('/api/v1/guest-order/delete', [
                'product_id' => $productId,
                'order_number' => $order->number,
            ])
            ->assertStatus(404);

        $this->assertDatabaseHas('order_items', ['order_id' => $order->id]);
    }

    public function test_guest_owner_can_delete_item_from_pending_order(): void
    {
        $guest = Guest::create(['cookie_id' => 'guest-a-cookie']);
        $order = $this->makeOrder(['guest_id' => $guest->id]);
        $productId = $order->orderItems->first()->product_id;

        $this->withHeaders(['guest-id' => (string) $guest->id])
            ->postJson('/api/v1/guest-order/delete', [
                'product_id' => $productId,
                'order_number' => $order->number,
            ])
            ->assertStatus(200);

        $this->assertDatabaseMissing('order_items', ['order_id' => $order->id]);
    }
}
