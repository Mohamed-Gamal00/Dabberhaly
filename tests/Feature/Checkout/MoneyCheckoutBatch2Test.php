<?php

namespace Tests\Feature\Checkout;

use App\Enums\OrderStatus;
use App\Events\UserOrder;
use App\Models\AppliedDiscountCode;
use App\Models\Cart;
use App\Models\DiscountCode;
use App\Models\MainCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingTypesAndPrice;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MoneyCheckoutBatch2Test extends TestCase
{
    use RefreshDatabase;

    private int $countryId;
    private int $cityId;
    private int $categoryId;
    private string $guestCookie = 'guest-cookie-123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->countryId = DB::table('countries')->insertGetId([
            'name_ar' => 'مصر', 'name_en' => 'Egypt', 'status' => 'used',
        ]);
        $this->cityId = DB::table('cities')->insertGetId([
            'name_ar' => 'القاهرة', 'name_en' => 'Cairo', 'status' => 'used',
            'country_id' => $this->countryId, 'shipping_price' => 25,
        ]);
        $this->categoryId = MainCategory::create(['name' => 'Test Category'])->id;

        ShippingTypesAndPrice::forceCreate([
            'id' => 1,
            'add_pickup_from_store' => 1,
            'add_normal_price' => 1,
            'normal_shipping_price' => 25,
            'add_wight_price' => 1,
            'weight_price' => 10,
            'add_price_based_on_city' => 1,
        ]);

        // VAT 0 and no free-shipping threshold unless a test changes them.
        Setting::create(['value_added_tax' => 0, 'free_shipping_threshold' => null]);
    }

    protected function tearDown(): void
    {
        Cart::setCookieId(null);

        parent::tearDown();
    }

    // ---------- helpers ----------

    private function makeProduct(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'price' => 200, 'discount_price' => null, 'quantity' => 50, 'weight' => 1,
            'category_id' => $this->categoryId,
        ], $overrides));
    }

    private function guestCartItem(Product $product, int $quantity = 1, array $overrides = []): void
    {
        Cart::withoutGlobalScope('cookie_id')->create(array_merge([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'status' => 0,
            'cookie_id' => $this->guestCookie,
        ], $overrides));
    }

    private function guestCheckoutPayload(array $overrides = []): array
    {
        return array_merge([
            'terms' => true,
            'first_name' => 'ضيف',
            'last_name' => 'تجريبي',
            'phone_number' => '01000000000',
            'country_id' => $this->countryId,
            'city_id' => $this->cityId,
            'address' => 'شارع التحرير 1',
            'payment_method' => 'cash_on_delivery',
            'fixed_shipping' => 1,
        ], $overrides);
    }

    private function postGuestCheckout(array $overrides = [])
    {
        return $this->postGuestCheckoutPayload($this->guestCheckoutPayload($overrides));
    }

    private function postGuestCheckoutPayload(array $payload)
    {
        Event::fake([UserOrder::class]);

        return $this->withHeaders(['guest-id' => $this->guestCookie])
            ->postJson('/api/v1/guest-checkout', $payload);
    }

    private function makeUserWithAddress(): array
    {
        $user = User::factory()->create(['password' => 'password']);
        $address = UserAddress::create([
            'user_id' => $user->id,
            'address_title' => 'العنوان الرئيسي',
            'first_name' => $user->first_name,
            'family_name' => $user->family_name,
            'phone_number' => $user->phone_number,
            'address' => 'شارع التحرير 1',
            'country_id' => $this->countryId,
            'city_id' => $this->cityId,
            'main_address' => true,
        ]);

        return [$user, $address];
    }

    private function userCartItem(User $user, Product $product, int $quantity = 1, array $overrides = []): void
    {
        Cart::withoutGlobalScope('cookie_id')->create(array_merge([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'status' => 0,
            'cookie_id' => 'test-cookie',
        ], $overrides));
    }

    private function makeDiscountCode(string $code, float $price, string $type = 'fixed'): DiscountCode
    {
        return DiscountCode::create([
            'name' => 'Test code',
            'code' => $code,
            'price' => $price,
            'status' => 'active',
            'discount_type' => $type,
            'number_of_used' => 5,
        ]);
    }

    // ---------- B2: payment_method required for guest checkout ----------

    public function test_guest_checkout_without_payment_method_is_rejected(): void
    {
        $this->guestCartItem($this->makeProduct(['price' => 100]));

        $payload = $this->guestCheckoutPayload();
        unset($payload['payment_method']);

        Event::fake([UserOrder::class]);
        $this->withHeaders(['guest-id' => $this->guestCookie])
            ->postJson('/api/v1/guest-checkout', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');

        $this->assertEquals(0, Order::count());
    }

    public function test_guest_checkout_with_disabled_payment_method_is_rejected(): void
    {
        Setting::first()->update(['payment_card_enabled' => false]);
        $this->guestCartItem($this->makeProduct(['price' => 100]));

        $this->postGuestCheckout(['payment_method' => 'card_payment'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');

        $this->assertEquals(0, Order::count());
    }

    // ---------- M1: one order address per guest order ----------

    public function test_guest_checkout_creates_one_order_address_for_three_items(): void
    {
        $this->guestCartItem($this->makeProduct(['price' => 100]));
        $this->guestCartItem($this->makeProduct(['price' => 100]));
        $this->guestCartItem($this->makeProduct(['price' => 100]));

        $this->postGuestCheckout()->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals(1, DB::table('order_addresses')->where('order_id', $order->id)->count());
    }

    // ---------- M2: server-side checkout summary ----------

    public function test_web_checkout_summary_with_fixed_code_and_vat(): void
    {
        Event::fake([UserOrder::class]);
        Setting::first()->update(['value_added_tax' => 15]);
        [$user, $address] = $this->makeUserWithAddress();
        $this->userCartItem($user, $this->makeProduct(['price' => 200]));
        Cart::setCookieId('test-cookie');
        $this->makeDiscountCode('FIX50', 50, 'fixed');

        $this->actingAs($user, 'web')
            ->post('/checkout/apply-discount', ['discount_code' => 'FIX50'])
            ->assertStatus(200)
            ->assertJsonPath('summary.discount', 50)
            ->assertJsonPath('summary.subtotal', 150)
            ->assertJsonPath('summary.tax_amount', 22.5);

        // Reload with the code already applied (session): the page
        // shows the discounted summary, not the full price.
        $this->actingAs($user, 'web')->get(route('checkout'))
            ->assertStatus(200)
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Checkout/Index')
                ->where('summary.subtotal_before_discount', 200)
                ->where('summary.subtotal', 150)
                ->where('summary.discount', 50)
                ->where('summary.tax_amount', 22.5)
                ->where('appliedDiscount.code', 'FIX50')
                ->where('total', 150)
            );

        // And the order the server creates charges exactly that.
        $this->actingAs($user, 'web')->post(route('checkout.store'), [
            'user_address' => (string) $address->id,
            'payment_method' => 'cash_on_delivery',
            'pickup_from_store' => '1',
        ])->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertEquals(200, (float) $order->totalBeforeDiscount);
        $this->assertEquals(150, (float) $order->orderItems->first()->price);
        $this->assertEquals(22.5, (float) $order->tax_amount);
        $this->assertEquals(172.5, (float) $order->total_price);
    }

    public function test_web_checkout_summary_for_product_specific_fixed_code(): void
    {
        [$user, $address] = $this->makeUserWithAddress();
        $productA = $this->makeProduct(['price' => 200]);
        $productB = $this->makeProduct(['price' => 100]);
        $this->userCartItem($user, $productA);
        $this->userCartItem($user, $productB);
        Cart::setCookieId('test-cookie');

        // Fixed 50 on product A only: the full 50 must NOT come off
        // the whole cart, and B is untouched.
        $code = $this->makeDiscountCode('FIX50A', 50, 'fixed');
        $code->products()->attach($productA->id);

        $this->actingAs($user, 'web')
            ->post('/checkout/apply-discount', ['discount_code' => 'FIX50A'])
            ->assertStatus(200)
            ->assertJsonPath('summary.discount', 50)
            ->assertJsonPath('summary.subtotal', 250);

        $this->actingAs($user, 'web')->get(route('checkout'))
            ->assertStatus(200)
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('summary.subtotal_before_discount', 300)
                ->where('summary.subtotal', 250)
                ->where('summary.discount', 50)
            );
    }

    public function test_web_checkout_summary_for_product_specific_percentage_code(): void
    {
        [$user, $address] = $this->makeUserWithAddress();
        $productA = $this->makeProduct(['price' => 200]);
        $productB = $this->makeProduct(['price' => 100]);
        $this->userCartItem($user, $productA);
        $this->userCartItem($user, $productB);
        Cart::setCookieId('test-cookie');

        // 10% on product A only: 20 off, not 10% of the whole cart (30).
        $code = $this->makeDiscountCode('PCT10A', 10, 'percentage');
        $code->products()->attach($productA->id);

        $this->actingAs($user, 'web')
            ->post('/checkout/apply-discount', ['discount_code' => 'PCT10A'])
            ->assertStatus(200)
            ->assertJsonPath('summary.discount', 20)
            ->assertJsonPath('summary.subtotal', 280);
    }

    public function test_remove_discount_returns_zero_discount_summary(): void
    {
        [$user, $address] = $this->makeUserWithAddress();
        $this->userCartItem($user, $this->makeProduct(['price' => 200]));
        Cart::setCookieId('test-cookie');
        $this->makeDiscountCode('FIX50', 50, 'fixed');

        $this->actingAs($user, 'web')
            ->post('/checkout/apply-discount', ['discount_code' => 'FIX50'])
            ->assertStatus(200);

        $this->actingAs($user, 'web')
            ->post('/checkout/remove-discount')
            ->assertStatus(200)
            ->assertJsonPath('summary.discount', 0)
            ->assertJsonPath('summary.subtotal', 200);
    }

    public function test_web_cart_total_ignores_stale_discounted_price(): void
    {
        [$user, $address] = $this->makeUserWithAddress();
        $this->userCartItem($user, $this->makeProduct(['price' => 200]), 1, ['discounted_price' => 10]);
        Cart::setCookieId('test-cookie');

        $this->actingAs($user, 'web')->get(route('checkout'))
            ->assertStatus(200)
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('total', 200)
                ->where('summary.subtotal', 200)
                ->where('summary.discount', 0)
                ->where('appliedDiscount', null)
            );
    }

    // ---------- M3: cart totals match checkout totals ----------

    public function test_guest_cart_total_uses_offer_price(): void
    {
        $product = $this->makeProduct(['price' => 100, 'discount_price' => 70]);
        $this->guestCartItem($product);

        $this->withHeaders(['guest-id' => $this->guestCookie])
            ->getJson('/api/v1/guest-cart/')
            ->assertStatus(200)
            ->assertJsonPath('data.total_price', 70);
    }

    public function test_guest_cart_total_uses_active_coupon_and_ignores_stale_discount(): void
    {
        $this->makeDiscountCode('FIX50', 50, 'fixed');
        $this->guestCartItem($this->makeProduct(['price' => 200]), 1, ['discounted_price' => 10]);

        // Stale leftover, no code applied: full price.
        $this->withHeaders(['guest-id' => $this->guestCookie])
            ->getJson('/api/v1/guest-cart/')
            ->assertStatus(200)
            ->assertJsonPath('data.total_price', 200);

        // Apply the code for real: the coupon price counts now.
        $this->withHeaders(['guest-id' => $this->guestCookie])
            ->postJson('/api/v1/guest-check-discount', ['discount_code' => 'FIX50'])
            ->assertStatus(200);

        $this->withHeaders(['guest-id' => $this->guestCookie])
            ->getJson('/api/v1/guest-cart/')
            ->assertStatus(200)
            ->assertJsonPath('data.total_price', 150);
    }

    public function test_user_cart_total_uses_offer_price(): void
    {
        [$user, $address] = $this->makeUserWithAddress();
        $this->userCartItem($user, $this->makeProduct(['price' => 100, 'discount_price' => 70]));

        $this->actingAs($user, 'user')
            ->getJson('/api/v1/cart/')
            ->assertStatus(200)
            ->assertJsonPath('data.total_price', 70);
    }

    public function test_user_cart_total_uses_active_coupon_and_ignores_stale_discount(): void
    {
        [$user, $address] = $this->makeUserWithAddress();
        $this->userCartItem($user, $this->makeProduct(['price' => 200]), 1, ['discounted_price' => 10]);
        $this->makeDiscountCode('FIX50', 50, 'fixed');

        // Stale leftover, no code applied: full price, zero discount.
        $this->actingAs($user, 'user')
            ->getJson('/api/v1/cart/')
            ->assertStatus(200)
            ->assertJsonPath('data.total_price', 200)
            ->assertJsonPath('data.discounted_price', 0);

        // Apply the code for real: 150 charged, 50 discount.
        $this->actingAs($user, 'user')
            ->postJson('/api/v1/check-discount', ['discount_code' => 'FIX50'])
            ->assertStatus(200);

        $this->actingAs($user, 'user')
            ->getJson('/api/v1/cart/')
            ->assertStatus(200)
            ->assertJsonPath('data.total_price', 150)
            ->assertJsonPath('data.discounted_price', 50);
    }

    // ---------- M5: guest city shipping never 500s or ships free by mistake ----------

    public function test_guest_checkout_with_invalid_city_is_rejected(): void
    {
        $this->guestCartItem($this->makeProduct(['price' => 100]));

        $payload = $this->guestCheckoutPayload(['city_id' => 999999, 'shipping_based_on_city' => 1]);
        unset($payload['fixed_shipping']);

        Event::fake([UserOrder::class]);
        $this->withHeaders(['guest-id' => $this->guestCookie])
            ->postJson('/api/v1/guest-checkout', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('city_id');

        $this->assertEquals(0, Order::count());
    }

    public function test_guest_checkout_with_missing_saved_address_is_rejected_not_free_shipping(): void
    {
        $this->guestCartItem($this->makeProduct(['price' => 100]));

        $payload = $this->guestCheckoutPayload([
            'user_address' => '999999',
            'shipping_based_on_city' => 1,
        ]);
        unset($payload['fixed_shipping'], $payload['first_name'], $payload['last_name'],
            $payload['phone_number'], $payload['country_id'], $payload['city_id'], $payload['address']);

        $this->postGuestCheckoutPayload($payload)->assertStatus(422);
        $this->assertEquals(0, Order::count());
    }

    public function test_guest_checkout_with_saved_address_missing_city_is_rejected(): void
    {
        $this->guestCartItem($this->makeProduct(['price' => 100]));
        [$user, $address] = $this->makeUserWithAddress();
        $address->update(['city_id' => null]);

        $payload = $this->guestCheckoutPayload([
            'user_address' => (string) $address->id,
            'shipping_based_on_city' => 1,
        ]);
        unset($payload['fixed_shipping'], $payload['first_name'], $payload['last_name'],
            $payload['phone_number'], $payload['country_id'], $payload['city_id'], $payload['address']);

        $this->postGuestCheckoutPayload($payload)->assertStatus(422);
        $this->assertEquals(0, Order::count());
    }

    // ---------- M6: soft-deleted product in a cart ----------

    public function test_guest_checkout_with_soft_deleted_product_returns_clean_error(): void
    {
        $product = $this->makeProduct(['price' => 100]);
        $this->guestCartItem($product);
        $product->delete();

        $response = $this->postGuestCheckout();
        $response->assertStatus(422);
        $this->assertStringContainsString('غير متاح', $response->json('message'));
        $this->assertEquals(0, Order::count());
    }

    public function test_guest_cart_with_soft_deleted_product_returns_clean_error(): void
    {
        $product = $this->makeProduct(['price' => 100]);
        $this->guestCartItem($product);
        $product->delete();

        $response = $this->withHeaders(['guest-id' => $this->guestCookie])
            ->getJson('/api/v1/guest-cart/');
        $response->assertStatus(422);
        $this->assertStringContainsString('غير متاح', $response->json('message'));
    }

    public function test_user_cart_with_soft_deleted_product_returns_clean_error(): void
    {
        [$user, $address] = $this->makeUserWithAddress();
        $product = $this->makeProduct(['price' => 100]);
        $this->userCartItem($user, $product);
        $product->delete();

        $response = $this->actingAs($user, 'user')->getJson('/api/v1/cart/');
        $response->assertStatus(422);
        $this->assertStringContainsString('غير متاح', $response->json('message'));
    }

    // ---------- #8: order detail exposes the tax line ----------

    public function test_order_detail_includes_tax_subtotal_and_discount(): void
    {
        [$user, $address] = $this->makeUserWithAddress();
        $product = $this->makeProduct(['price' => 200]);

        $order = Order::createWithUniqueNumber([
            'user_id' => $user->id,
            'payment_method' => 'cash_on_delivery',
            'status' => OrderStatus::Pending,
            'payment_status' => 'unpaid',
            'shipping_price' => 25,
            'tax_amount' => 30,
            'totalBeforeDiscount' => 250,
            'total_price' => 255,
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'product_name' => 'A', 'price' => 200, 'quantity' => 1,
        ]);

        $this->actingAs($user, 'user')
            ->getJson("/api/v1/user_show_order/{$order->number}")
            ->assertStatus(200)
            ->assertJsonPath('data.order.tax_amount', 30)
            ->assertJsonPath('data.order.subtotal', 200)
            ->assertJsonPath('data.order.total_before_discount', 250)
            ->assertJsonPath('data.order.discount_amount', 50)
            // total_price is a decimal column, serialised as "255.00".
            ->assertJsonPath('data.order.total_price', '255.00');
    }
}
