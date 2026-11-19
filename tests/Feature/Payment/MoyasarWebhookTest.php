<?php

namespace Tests\Feature\Payment;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MoyasarWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'payment_method' => 'card_payment',
            'status' => OrderStatus::Pending,
            'payment_status' => 'unpaid',
            'total_price' => 275,
            'shipping_price' => 25,
        ], $overrides));
    }

    private function moyasarPayload(Order $order, array $overrides = []): array
    {
        return array_merge([
            'id' => 'pay-webhook-1',
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

    private function fakeMoyasar(Order $order, array $overrides = []): void
    {
        Http::fake([
            'api.moyasar.com/*' => Http::response($this->moyasarPayload($order, $overrides), 200),
        ]);
    }

    public function test_webhook_marks_paid_when_moyasar_confirms(): void
    {
        $order = $this->makeOrder();
        $this->fakeMoyasar($order);

        $this->postJson('/api/v1/webhooks/moyasar', $this->moyasarPayload($order))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Payment successful');

        $this->assertEquals('paid', $order->fresh()->payment_status);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'moyasar_payment_id' => 'pay-webhook-1',
            'status' => 'paid',
        ]);
    }

    public function test_webhook_is_idempotent(): void
    {
        $order = $this->makeOrder();
        $this->fakeMoyasar($order);
        $payload = $this->moyasarPayload($order);

        $this->postJson('/api/v1/webhooks/moyasar', $payload)->assertStatus(200);
        $this->postJson('/api/v1/webhooks/moyasar', $payload)->assertStatus(200);

        $this->assertEquals('paid', $order->fresh()->payment_status);
        $this->assertEquals(1, $order->fresh()->transactions()->count());
    }

    public function test_webhook_without_payment_id_does_not_fail_the_order(): void
    {
        $order = $this->makeOrder();

        $this->postJson('/api/v1/webhooks/moyasar', ['status' => 'paid'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'ignored');

        $this->assertEquals('unpaid', $order->fresh()->payment_status);
        $this->assertDatabaseMissing('payment_transactions', ['order_id' => $order->id]);
    }

    public function test_webhook_with_wrong_amount_does_not_mark_paid(): void
    {
        $order = $this->makeOrder();
        $this->fakeMoyasar($order, ['amount' => 100]);

        $this->postJson('/api/v1/webhooks/moyasar', $this->moyasarPayload($order, ['amount' => 100]))
            ->assertStatus(200);

        $this->assertEquals('unpaid', $order->fresh()->payment_status);
    }

    public function test_webhook_wrapped_in_data_key_is_accepted(): void
    {
        $order = $this->makeOrder();
        $this->fakeMoyasar($order);

        $this->postJson('/api/v1/webhooks/moyasar', [
            'type' => 'payment_paid',
            'data' => $this->moyasarPayload($order),
        ])->assertStatus(200);

        $this->assertEquals('paid', $order->fresh()->payment_status);
    }
}
