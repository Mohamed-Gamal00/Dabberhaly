<?php

namespace Tests\Feature\Discount;

use App\Events\UserOrder;
use App\Models\Cart;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingTypesAndPrice;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DiscountConsumptionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cart::setCookieId(null);

        parent::tearDown();
    }

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

    /** Shipping row + settings + a user with a saved address, for API checkout. */
    private function seedApiCheckoutFixtures(): array
    {
        $countryId = DB::table('countries')->insertGetId([
            'name_ar' => 'مصر', 'name_en' => 'Egypt', 'status' => 'used',
        ]);
        $cityId = DB::table('cities')->insertGetId([
            'name_ar' => 'القاهرة', 'name_en' => 'Cairo', 'status' => 'used',
            'country_id' => $countryId, 'shipping_price' => 25,
        ]);

        ShippingTypesAndPrice::forceCreate([
            'id' => 1,
            'add_pickup_from_store' => 1,
            'add_normal_price' => 1,
            'normal_shipping_price' => 25,
            'add_wight_price' => 1,
            'weight_price' => 10,
            'add_price_based_on_city' => 1,
        ]);

        Setting::create(['value_added_tax' => 0, 'free_shipping_threshold' => null]);

        $user = User::factory()->create(['password' => 'password']);
        $address = UserAddress::create([
            'user_id' => $user->id,
            'address_title' => 'العنوان الرئيسي',
            'first_name' => $user->first_name,
            'family_name' => $user->family_name,
            'phone_number' => $user->phone_number,
            'address' => 'شارع التحرير 1',
            'country_id' => $countryId,
            'city_id' => $cityId,
            'main_address' => true,
        ]);

        return [$user, $address];
    }

    private function apiCheckout(User $user, UserAddress $address)
    {
        Event::fake([UserOrder::class]);

        return $this->actingAs($user, 'user')->postJson('/api/v1/checkout', [
            'user_address' => (string) $address->id,
            'payment_method' => 'cash_on_delivery',
            'fixed_shipping' => '1',
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

    public function test_api_user_checkout_stores_applied_discount_code_id(): void
    {
        [$user, $address] = $this->seedApiCheckoutFixtures();
        $code = $this->makeCode();
        $product = Product::factory()->create(['price' => 200, 'quantity' => 50]);

        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 0,
            'quantity' => 1,
            'cookie_id' => 'test-cookie',
        ]);

        // Apply via the API: consumed at apply time (3 -> 2 uses).
        $this->actingAs($user, 'user')
            ->postJson('/api/v1/check-discount', ['discount_code' => 'TEST10'])
            ->assertOk();
        $this->assertEquals(2, $code->fresh()->number_of_used);

        $this->apiCheckout($user, $address)->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        // The order records the code that discounted it...
        $this->assertEquals($code->id, $order->discount_code_id);
        // ...without consuming it a second time.
        $this->assertEquals(2, $code->fresh()->number_of_used);
        // 190 discounted + 25 shipping.
        $this->assertEquals(215, (float) $order->total_price);
    }

    public function test_api_user_checkout_without_a_code_stores_null_discount_code_id(): void
    {
        [$user, $address] = $this->seedApiCheckoutFixtures();
        $product = Product::factory()->create(['price' => 200, 'quantity' => 50]);

        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 0,
            'quantity' => 1,
            'cookie_id' => 'test-cookie',
        ]);

        $this->apiCheckout($user, $address)->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertNull($order->discount_code_id);
        $this->assertEquals(225, (float) $order->total_price);
    }

    public function test_api_user_second_checkout_does_not_reuse_leftover_applied_row(): void
    {
        [$user, $address] = $this->seedApiCheckoutFixtures();
        $code = $this->makeCode();
        $product = Product::factory()->create(['price' => 200, 'quantity' => 50]);

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
        $this->apiCheckout($user, $address)->assertStatus(201);
        $this->assertEquals($code->id, Order::latest('id')->first()->discount_code_id);

        // New cart, no code applied this time. The usage row from the
        // first order still exists (rows are never deleted) — it must
        // not leak onto the second order.
        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 0,
            'quantity' => 1,
            'cookie_id' => 'test-cookie',
        ]);

        $this->apiCheckout($user, $address)->assertStatus(201);

        $secondOrder = Order::latest('id')->first();
        $this->assertNull($secondOrder->discount_code_id);
        $this->assertEquals(225, (float) $secondOrder->total_price);
    }

    /* ---------- guest checkout ---------- */

    private function guestCheckoutPayload(UserAddress $address): array
    {
        return [
            'terms' => true,
            'first_name' => 'ضيف',
            'last_name' => 'تجريبي',
            'phone_number' => '01000000000',
            'country_id' => $address->country_id,
            'city_id' => $address->city_id,
            'address' => 'شارع التحرير 1',
            'payment_method' => 'cash_on_delivery',
            'fixed_shipping' => 1,
        ];
    }

    private function guestCheckout(UserAddress $address)
    {
        Event::fake([UserOrder::class]);

        return $this->withHeaders(['guest-id' => 'guest-cookie'])
            ->postJson('/api/v1/guest-checkout', $this->guestCheckoutPayload($address));
    }

    public function test_guest_checkout_stores_applied_discount_code_id(): void
    {
        [, $address] = $this->seedApiCheckoutFixtures();
        $code = $this->makeCode();
        $product = Product::factory()->create(['price' => 200, 'quantity' => 50]);

        Cart::withoutGlobalScope('cookie_id')->create([
            'product_id' => $product->id,
            'status' => 0,
            'quantity' => 1,
            'cookie_id' => 'guest-cookie',
        ]);

        $this->withHeaders(['guest-id' => 'guest-cookie'])
            ->postJson('/api/v1/guest-check-discount', ['discount_code' => 'TEST10'])
            ->assertOk();

        $this->guestCheckout($address)->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertEquals($code->id, $order->discount_code_id);
        $this->assertEquals(215, (float) $order->total_price);
    }

    public function test_guest_checkout_without_a_code_stores_null_discount_code_id(): void
    {
        [, $address] = $this->seedApiCheckoutFixtures();
        $product = Product::factory()->create(['price' => 200, 'quantity' => 50]);

        Cart::withoutGlobalScope('cookie_id')->create([
            'product_id' => $product->id,
            'status' => 0,
            'quantity' => 1,
            'cookie_id' => 'guest-cookie',
        ]);

        $this->guestCheckout($address)->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertNull($order->discount_code_id);
        $this->assertEquals(225, (float) $order->total_price);
    }

    public function test_guest_second_checkout_does_not_reuse_leftover_applied_row(): void
    {
        [, $address] = $this->seedApiCheckoutFixtures();
        $code = $this->makeCode();
        $product = Product::factory()->create(['price' => 200, 'quantity' => 50]);

        Cart::withoutGlobalScope('cookie_id')->create([
            'product_id' => $product->id,
            'status' => 0,
            'quantity' => 1,
            'cookie_id' => 'guest-cookie',
        ]);

        $this->withHeaders(['guest-id' => 'guest-cookie'])
            ->postJson('/api/v1/guest-check-discount', ['discount_code' => 'TEST10'])
            ->assertOk();
        $this->guestCheckout($address)->assertStatus(201);
        $this->assertEquals($code->id, Order::latest('id')->first()->discount_code_id);

        // New cart, no code applied this time. The usage row from the
        // first order still exists (rows are never deleted) — it must
        // not leak onto the second order.
        Cart::withoutGlobalScope('cookie_id')->create([
            'product_id' => $product->id,
            'status' => 0,
            'quantity' => 1,
            'cookie_id' => 'guest-cookie',
        ]);

        $this->guestCheckout($address)->assertStatus(201);

        $secondOrder = Order::latest('id')->first();
        $this->assertNull($secondOrder->discount_code_id);
        $this->assertEquals(225, (float) $secondOrder->total_price);
    }
}
