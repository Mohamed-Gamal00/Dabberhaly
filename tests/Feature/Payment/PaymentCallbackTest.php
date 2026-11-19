<?php

namespace Tests\Feature\Payment;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentCallbackTest extends TestCase
{
    use RefreshDatabase;

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

    private function moyasarPayload(Order $order, array $overrides = []): array
    {
        return array_merge([
            'id' => 'pay-test-1',
            'status' => 'paid',
            'amount' => $order->expectedPaymentAmountHalalas(),
            'currency' => 'SAR',
            'description' => "Order number {$order->number}",
            'source' => [
                'type' => 'creditcard',
                'brand' => 'visa',
                'number' => '4111111111111111',
            ],
        ], $overrides);
    }

    public function test_api_callback_with_unknown_order_does_not_crash(): void
    {
        $this->get('/api/v1/payment-page/999999999/payment/callback?id=pay-x')
            ->assertRedirect('/');
    }

    public function test_inertia_callback_marks_paid_only_when_amount_currency_and_order_match(): void
    {
        $order = $this->makeOrder();
        Http::fake([
            'api.moyasar.com/*' => Http::response($this->moyasarPayload($order), 200),
        ]);

        $this->get("/payment/{$order->number}/callback?id=pay-test-1")
            ->assertOk();

        $this->assertEquals('paid', $order->fresh()->payment_status);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'status' => 'paid',
        ]);
    }

    public function test_inertia_callback_rejects_payment_with_wrong_amount(): void
    {
        $order = $this->makeOrder();
        Http::fake([
            'api.moyasar.com/*' => Http::response($this->moyasarPayload($order, [
                'amount' => 100, // 1 SAR — far below the 250 SAR order
            ]), 200),
        ]);

        $this->get("/payment/{$order->number}/callback?id=pay-test-1")
            ->assertOk();

        $this->assertEquals('failed', $order->fresh()->payment_status);
    }

    public function test_inertia_callback_rejects_payment_for_another_order(): void
    {
        $order = $this->makeOrder();
        Http::fake([
            'api.moyasar.com/*' => Http::response($this->moyasarPayload($order, [
                'description' => 'Order number 2026000999',
            ]), 200),
        ]);

        $this->get("/payment/{$order->number}/callback?id=pay-test-1")
            ->assertOk();

        $this->assertEquals('failed', $order->fresh()->payment_status);
    }

    public function test_api_callback_marks_paid_when_moyasar_confirms(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $order = $this->makeOrder(['user_id' => $user->id]);
        Http::fake([
            'api.moyasar.com/*' => Http::response($this->moyasarPayload($order), 200),
        ]);

        $this->get("/api/v1/payment-page/{$order->number}/payment/callback?id=pay-test-1")
            ->assertOk()
            ->assertJsonPath('message', 'Payment successful');

        $this->assertEquals('paid', $order->fresh()->payment_status);
    }

    public function test_api_callback_rejects_underpaid_payment(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $order = $this->makeOrder(['user_id' => $user->id]);
        Http::fake([
            'api.moyasar.com/*' => Http::response($this->moyasarPayload($order, [
                'amount' => 100,
            ]), 200),
        ]);

        $this->get("/api/v1/payment-page/{$order->number}/payment/callback?id=pay-test-1")
            ->assertStatus(422);

        $this->assertEquals('failed', $order->fresh()->payment_status);
    }
}
