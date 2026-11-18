<?php

namespace Tests\Feature\Checkout;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckOut\CheckoutServices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_refuses_to_oversell(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $product = Product::factory()->stock(1)->create();

        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 0,
            'quantity' => 2,
            'cookie_id' => 'test-cookie',
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'payment_method' => 'cash',
            'total_price' => 100,
        ]);

        try {
            app(CheckoutServices::class)->createOrderItems($order, $user);
            $this->fail('Expected an out-of-stock exception.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('غير متوفرة', $e->getMessage());
        }

        $this->assertEquals(1, $product->fresh()->quantity);
        $this->assertDatabaseMissing('order_items', ['order_id' => $order->id]);
    }

    public function test_last_unit_stays_sellable_and_is_not_archived(): void
    {
        $product = Product::factory()->stock(1)->create();

        app(CheckoutServices::class)->disActiveProduct(collect([
            (object) ['product_id' => $product->id],
        ]));

        $this->assertEquals('active', $product->fresh()->status);
    }

    public function test_zero_stock_product_is_archived(): void
    {
        $product = Product::factory()->stock(0)->create();

        app(CheckoutServices::class)->disActiveProduct(collect([
            (object) ['product_id' => $product->id],
        ]));

        $this->assertEquals('archived', $product->fresh()->status);
    }
}
