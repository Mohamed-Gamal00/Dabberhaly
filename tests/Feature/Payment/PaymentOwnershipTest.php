<?php

namespace Tests\Feature\Payment;

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cart::setCookieId(null);

        parent::tearDown();
    }

    private function makeOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'payment_method' => 'card_payment',
            'status' => OrderStatus::Pending,
            'payment_status' => 'unpaid',
            'total_price' => 250,
            'shipping_price' => 25,
        ], $overrides));
    }

    public function test_web_payment_page_rejects_another_users_order(): void
    {
        $owner = User::factory()->create(['password' => 'password']);
        $attacker = User::factory()->create(['password' => 'password']);
        $order = $this->makeOrder(['user_id' => $owner->id]);

        $this->actingAs($attacker, 'web')
            ->get(route('user.payment', ['order_number' => $order->number]))
            ->assertNotFound();

        // The owner can still open it.
        $this->actingAs($owner, 'web')
            ->get(route('user.payment', ['order_number' => $order->number]))
            ->assertStatus(200);
    }

    public function test_web_payment_page_rejects_wrong_guest_cookie(): void
    {
        $order = $this->makeOrder(['cookie_id' => 'cookie-owner']);
        Cart::setCookieId('cookie-attacker');

        $this->get(route('user.payment', ['order_number' => $order->number]))
            ->assertNotFound();

        Cart::setCookieId('cookie-owner');
        $this->get(route('user.payment', ['order_number' => $order->number]))
            ->assertStatus(200);
    }

    public function test_success_page_rejects_another_users_order(): void
    {
        $owner = User::factory()->create(['password' => 'password']);
        $attacker = User::factory()->create(['password' => 'password']);
        $order = $this->makeOrder(['user_id' => $owner->id]);

        $this->actingAs($attacker, 'web')
            ->get(route('checkout.success', ['order_number' => $order->number]))
            ->assertNotFound();

        $this->actingAs($owner, 'web')
            ->get(route('checkout.success', ['order_number' => $order->number]))
            ->assertStatus(200);
    }

    public function test_success_page_rejects_wrong_guest_cookie(): void
    {
        $order = $this->makeOrder(['cookie_id' => 'cookie-owner']);
        Cart::setCookieId('cookie-attacker');

        $this->get(route('checkout.success', ['order_number' => $order->number]))
            ->assertNotFound();
    }

    public function test_api_payment_page_rejects_another_users_order(): void
    {
        $owner = User::factory()->create(['password' => 'password']);
        $attacker = User::factory()->create(['password' => 'password']);
        $order = $this->makeOrder(['user_id' => $owner->id]);

        $this->actingAs($attacker, 'user')
            ->get("/api/v1/payment-page/{$order->number}")
            ->assertStatus(404);

        $this->actingAs($owner, 'user')
            ->get("/api/v1/payment-page/{$order->number}")
            ->assertStatus(200);
    }

    public function test_api_payment_page_rejects_wrong_guest_id(): void
    {
        $order = $this->makeOrder(['cookie_id' => 'guest-owner']);

        $this->withHeaders(['guest-id' => 'guest-attacker'])
            ->get("/api/v1/payment-page/{$order->number}")
            ->assertStatus(404);

        $this->withHeaders(['guest-id' => 'guest-owner'])
            ->get("/api/v1/payment-page/{$order->number}")
            ->assertStatus(200);
    }
}
