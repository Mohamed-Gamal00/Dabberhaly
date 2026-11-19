<?php

namespace Tests\Feature\Discount;

use App\Models\Cart;
use App\Models\DiscountCode;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountConsumptionTest extends TestCase
{
    use RefreshDatabase;

    private function makeCode(int $uses = 3): DiscountCode
    {
        return DiscountCode::create([
            'name' => 'Test code',
            'code' => 'TEST10',
            'price' => 10,
            'status' => 'active',
            'discount_type' => 'fixed',
            'number_of_used' => $uses,
        ]);
    }

    public function test_api_apply_with_empty_cart_consumes_nothing(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $code = $this->makeCode();

        $this->actingAs($user, 'user')
            ->postJson('/api/v1/check-discount', ['discount_code' => 'TEST10'])
            ->assertOk();

        $this->assertEquals(3, $code->fresh()->number_of_used);
        $this->assertDatabaseMissing('applied_discount_codes', ['discount_id' => $code->id]);
    }

    public function test_api_apply_with_items_consumes_one_use(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $code = $this->makeCode();
        $product = Product::factory()->create();

        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 0,
            'quantity' => 1,
            'cookie_id' => 'test-cookie',
        ]);

        $this->actingAs($user, 'user')
            ->postJson('/api/v1/check-discount', ['discount_code' => 'TEST10'])
            ->assertOk();

        $this->assertEquals(2, $code->fresh()->number_of_used);
        $this->assertDatabaseHas('applied_discount_codes', ['discount_id' => $code->id]);
    }

    public function test_web_apply_does_not_consume_until_order(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $code = $this->makeCode();
        $product = Product::factory()->create();

        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 0,
            'quantity' => 1,
            'cookie_id' => 'test-cookie',
        ]);

        $this->actingAs($user, 'web')
            ->postJson('/checkout/apply-discount', ['discount_code' => 'TEST10'])
            ->assertOk();

        $this->assertEquals(3, $code->fresh()->number_of_used);
        $this->assertDatabaseMissing('applied_discount_codes', ['discount_id' => $code->id]);
    }
}
