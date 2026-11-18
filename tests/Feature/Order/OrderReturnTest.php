<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Events\OrderReturned;
use App\Listeners\SendReturnNotification;
use App\Models\Admin;
use App\Models\Guest;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderReturnedAdminNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderReturnTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['password' => 'password'], $overrides));
    }

    private function makeOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'status' => OrderStatus::Pending,
            'return_order' => false,
        ], $overrides));
    }

    // --- Web (logged-in user) ---

    public function test_web_user_can_return_a_pending_order(): void
    {
        Event::fake();
        $user = $this->makeUser();
        $order = $this->makeOrder(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'web')
            ->post(route('user.return_products.store'), ['return_order_id' => $order->id]);

        $response->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'return_order' => true]);
        Event::assertDispatched(OrderReturned::class);
    }

    public function test_web_user_cannot_return_a_confirmed_order(): void
    {
        Event::fake();
        $user = $this->makeUser();
        $order = $this->makeOrder(['user_id' => $user->id, 'status' => OrderStatus::Confirmed]);

        $response = $this->actingAs($user, 'web')
            ->post(route('user.return_products.store'), ['return_order_id' => $order->id]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'return_order' => false]);
        Event::assertNotDispatched(OrderReturned::class);
    }

    public function test_web_user_returning_an_already_returned_order_is_idempotent(): void
    {
        Event::fake();
        $user = $this->makeUser();
        $order = $this->makeOrder(['user_id' => $user->id, 'return_order' => true]);

        $response = $this->actingAs($user, 'web')
            ->post(route('user.return_products.store'), ['return_order_id' => $order->id]);

        $response->assertOk();
        Event::assertNotDispatched(OrderReturned::class);
    }

    // --- API (logged-in user) ---

    public function test_api_user_cannot_return_another_users_order(): void
    {
        Event::fake();
        $user = $this->makeUser();
        $other = $this->makeUser(['email' => 'other@example.com', 'phone_number' => '01111111111']);
        $order = $this->makeOrder(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'user')
            ->postJson('/api/v1/user-return-orders/store', ['return_order_id' => $order->id]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'return_order' => false]);
        Event::assertNotDispatched(OrderReturned::class);
    }

    public function test_api_user_can_return_own_pending_order(): void
    {
        Event::fake();
        $user = $this->makeUser();
        $order = $this->makeOrder(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'user')
            ->postJson('/api/v1/user-return-orders/store', ['return_order_id' => $order->id]);

        $response->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'return_order' => true]);
        Event::assertDispatched(OrderReturned::class);
    }

    public function test_api_user_cannot_return_a_delivered_order(): void
    {
        Event::fake();
        $user = $this->makeUser();
        $order = $this->makeOrder(['user_id' => $user->id, 'status' => OrderStatus::Delivered]);

        $response = $this->actingAs($user, 'user')
            ->postJson('/api/v1/user-return-orders/store', ['return_order_id' => $order->id]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'return_order' => false]);
        Event::assertNotDispatched(OrderReturned::class);
    }

    // --- API (guest) ---

    public function test_api_guest_cannot_return_another_guests_order(): void
    {
        Event::fake();
        $guest = Guest::create(['cookie_id' => 'guest-cookie-1']);
        $otherGuest = Guest::create(['cookie_id' => 'guest-cookie-2']);
        $order = $this->makeOrder(['guest_id' => $otherGuest->id]);

        $response = $this->withHeaders(['guest-id' => (string) $guest->id])
            ->postJson('/api/v1/guest-return-orders/store', ['return_order_id' => $order->id]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'return_order' => false]);
        Event::assertNotDispatched(OrderReturned::class);
    }

    public function test_api_guest_can_return_own_pending_order(): void
    {
        Event::fake();
        $guest = Guest::create(['cookie_id' => 'guest-cookie-1']);
        $order = $this->makeOrder(['guest_id' => $guest->id]);

        $response = $this->withHeaders(['guest-id' => (string) $guest->id])
            ->postJson('/api/v1/guest-return-orders/store', ['return_order_id' => $order->id]);

        $response->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'return_order' => true]);
        Event::assertDispatched(OrderReturned::class);
    }

    // --- Admin notification ---

    public function test_return_listener_notifies_super_admins_only(): void
    {
        $superAdmin = Admin::create([
            'name' => 'Super', 'email' => 'super@example.com',
            'password' => 'password', 'super_admin' => true,
        ]);
        $regularAdmin = Admin::create([
            'name' => 'Regular', 'email' => 'regular@example.com',
            'password' => 'password', 'super_admin' => false,
        ]);
        $order = $this->makeOrder(['return_order' => true]);

        (new SendReturnNotification)->handle(new OrderReturned($order));

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $superAdmin->id,
            'type' => OrderReturnedAdminNotification::class,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $regularAdmin->id,
            'type' => OrderReturnedAdminNotification::class,
        ]);

        $notification = $superAdmin->notifications()->first();
        $this->assertSame(route('return_orders.show', $order->id, false), $notification->data['url']);
    }
}
