<?php

namespace Tests\Feature\Checkout;

use App\Enums\OrderStatus;
use App\Events\UserOrder;
use App\Models\Cart;
use App\Models\DiscountCode;
use App\Models\Guest;
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

class MoneyCheckoutFixesTest extends TestCase
{
    use RefreshDatabase;

    private int $countryId;
    private int $cityId;
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
        Event::fake([UserOrder::class]);

        return $this->withHeaders(['guest-id' => $this->guestCookie])
            ->postJson('/api/v1/guest-checkout', $this->guestCheckoutPayload($overrides));
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

    // ---------- 1. guest checkout total includes shipping + discount_price ----------

    public function test_guest_checkout_total_includes_shipping(): void
    {
        $this->guestCartItem($this->makeProduct(['price' => 100]));
        $this->guestCartItem($this->makeProduct(['price' => 100]));

        // Two items of 100 each + fixed shipping 25.
        $this->postGuestCheckout()->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals(25, (float) $order->shipping_price);
        $this->assertEquals(225, (float) $order->total_price);
    }

    public function test_guest_checkout_respects_discount_price(): void
    {
        // Offer price 70 on a 100 product.
        $product = $this->makeProduct(['price' => 100, 'discount_price' => 70]);
        $this->guestCartItem($product);

        $this->postGuestCheckout()->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertEquals(70, (float) $order->totalBeforeDiscount);
        $this->assertEquals(95, (float) $order->total_price); // 70 + 25 shipping
        $this->assertEquals(70, (float) $order->orderItems->first()->price);
    }

    // ---------- 2. guest discount formula ----------

    public function test_guest_fixed_discount_applies_once_per_order_not_per_item(): void
    {
        $this->makeDiscountCode('FIX50', 50, 'fixed');
        $this->guestCartItem($this->makeProduct(['price' => 200]));
        $this->guestCartItem($this->makeProduct(['price' => 100]));

        $this->withHeaders(['guest-id' => $this->guestCookie])
            ->postJson('/api/v1/guest-check-discount', ['discount_code' => 'FIX50'])
            ->assertStatus(200);

        // First line absorbs the whole 50; the second line is untouched (null).
        $prices = Cart::withoutGlobalScope('cookie_id')
            ->where('cookie_id', $this->guestCookie)->where('status', 0)
            ->orderBy('id')->pluck('discounted_price')->map(fn ($p) => $p === null ? null : (float) $p)->all();
        $this->assertEquals([150.0, null], $prices);

        $this->postGuestCheckout()->assertStatus(201);
        $order = Order::latest('id')->first();
        // 150 + 100 + 25 shipping = 275 (a per-item bug would give 225).
        $this->assertEquals(275, (float) $order->total_price);
    }

    public function test_guest_percentage_discount_uses_price_minus_percentage(): void
    {
        $this->makeDiscountCode('PCT10', 10, 'percentage');
        $this->guestCartItem($this->makeProduct(['price' => 200]));

        $this->withHeaders(['guest-id' => $this->guestCookie])
            ->postJson('/api/v1/guest-check-discount', ['discount_code' => 'PCT10'])
            ->assertStatus(200);

        $item = Cart::withoutGlobalScope('cookie_id')
            ->where('cookie_id', $this->guestCookie)->where('status', 0)->first();
        // 10% off 200 = 180 — not 20 (the old inverted formula).
        $this->assertEquals(180, (float) $item->discounted_price);
    }

    // ---------- 3. stale discounted_price + remove discount ----------

    public function test_web_checkout_ignores_stale_discounted_price_without_a_code(): void
    {
        Event::fake([UserOrder::class]);
        [$user, $address] = $this->makeUserWithAddress();
        $product = $this->makeProduct(['price' => 200]);
        // Leftover from an abandoned discount: no session code, no usage row.
        $this->userCartItem($user, $product, 1, ['discounted_price' => 10]);
        Cart::setCookieId('test-cookie');

        $this->actingAs($user, 'web')->post(route('checkout.store'), [
            'user_address' => (string) $address->id,
            'payment_method' => 'cash_on_delivery',
            'pickup_from_store' => '1',
        ])->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertEquals(200, (float) $order->total_price);
        $this->assertEquals(200, (float) $order->orderItems->first()->price);
    }

    public function test_remove_discount_endpoint_clears_session_and_cart_prices(): void
    {
        [$user, $address] = $this->makeUserWithAddress();
        $this->makeDiscountCode('FIX50', 50, 'fixed');
        $product = $this->makeProduct(['price' => 200]);
        $this->userCartItem($user, $product);
        Cart::setCookieId('test-cookie');

        $this->actingAs($user, 'web')
            ->post('/checkout/apply-discount', ['discount_code' => 'FIX50'])
            ->assertStatus(200);
        $this->assertEquals(150, (float) Cart::withoutGlobalScope('cookie_id')
            ->where('user_id', $user->id)->where('status', 0)->first()->discounted_price);

        $this->actingAs($user, 'web')
            ->post('/checkout/remove-discount')
            ->assertStatus(200);

        $item = Cart::withoutGlobalScope('cookie_id')
            ->where('user_id', $user->id)->where('status', 0)->first();
        $this->assertNull($item->discounted_price);
        $this->assertFalse(session()->has('applied_discount_code_id'));
    }

    // ---------- 4. free shipping ----------

    public function test_guest_free_shipping_at_threshold(): void
    {
        Setting::first()->update(['free_shipping_threshold' => 500]);
        $this->guestCartItem($this->makeProduct(['price' => 600]));

        $this->postGuestCheckout()->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertEquals(0, (float) $order->shipping_price);
        $this->assertEquals(600, (float) $order->total_price);
    }

    public function test_guest_no_free_shipping_below_threshold(): void
    {
        Setting::first()->update(['free_shipping_threshold' => 500]);
        $this->guestCartItem($this->makeProduct(['price' => 400]));

        $this->postGuestCheckout()->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertEquals(25, (float) $order->shipping_price);
        $this->assertEquals(425, (float) $order->total_price);
    }

    public function test_web_free_shipping_at_threshold(): void
    {
        Event::fake([UserOrder::class]);
        Setting::first()->update(['free_shipping_threshold' => 500]);
        [$user, $address] = $this->makeUserWithAddress();
        $this->userCartItem($user, $this->makeProduct(['price' => 500]));
        Cart::setCookieId('test-cookie');

        $this->actingAs($user, 'web')->post(route('checkout.store'), [
            'user_address' => (string) $address->id,
            'payment_method' => 'cash_on_delivery',
            'fixed_shipping' => '1',
        ])->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertEquals(0, (float) $order->shipping_price);
        $this->assertEquals(500, (float) $order->total_price);
    }

    public function test_api_user_free_shipping_at_threshold(): void
    {
        Event::fake([UserOrder::class]);
        Setting::first()->update(['free_shipping_threshold' => 500]);
        [$user, $address] = $this->makeUserWithAddress();
        $this->userCartItem($user, $this->makeProduct(['price' => 500]));

        $this->actingAs($user, 'user')->postJson('/api/v1/checkout', [
            'user_address' => (string) $address->id,
            'payment_method' => 'cash_on_delivery',
            'fixed_shipping' => '1',
        ])->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertEquals(0, (float) $order->shipping_price);
        $this->assertEquals(500, (float) $order->total_price);
    }

    // ---------- 5. guest tracking ----------

    public function test_create_guest_returns_cookie_id_and_order_is_listable_and_trackable(): void
    {
        $guestResponse = $this->postJson('/api/v1/create-guest')->assertStatus(200);
        $cookieId = $guestResponse->json('data.cookie_id');
        $this->assertNotEmpty($cookieId);

        $product = $this->makeProduct(['price' => 100]);
        // OrderResource serialises products through ProductsResource,
        // which reads the product's parent category (null without one).
        $category = \App\Models\MainCategory::create(['name' => 'Test Category']);
        $product->update(['category_id' => $category->id]);
        $this->withHeaders(['guest-id' => $cookieId])
            ->postJson('/api/v1/guest-cart/add', ['product_id' => $product->id, 'quantity' => 1])
            ->assertStatus(201);

        $this->withHeaders(['guest-id' => $cookieId])
            ->postJson('/api/v1/guest-checkout', $this->guestCheckoutPayload())
            ->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);

        $list = $this->withHeaders(['guest-id' => $cookieId])
            ->getJson('/api/v1/guest-orders')->assertStatus(200);
        $this->assertStringContainsString((string) $order->number, $list->getContent());

        $this->withHeaders(['guest-id' => $cookieId])
            ->getJson("/api/v1/guest-order/{$order->number}")
            ->assertStatus(200);
    }

    // ---------- 7. VAT ----------

    public function test_vat_applied_consistently_in_all_three_paths(): void
    {
        Setting::first()->update(['value_added_tax' => 15]);

        // Guest: 200 + 25 shipping + 30 VAT = 255.
        $this->guestCartItem($this->makeProduct(['price' => 200]));
        $this->postGuestCheckout()->assertStatus(201);
        $guestOrder = Order::latest('id')->first();
        $this->assertEquals(30, (float) $guestOrder->tax_amount);
        $this->assertEquals(255, (float) $guestOrder->total_price);

        // Web: 200 pickup + 30 VAT = 230.
        Event::fake([UserOrder::class]);
        [$user, $address] = $this->makeUserWithAddress();
        $this->userCartItem($user, $this->makeProduct(['price' => 200]));
        Cart::setCookieId('test-cookie');
        $this->actingAs($user, 'web')->post(route('checkout.store'), [
            'user_address' => (string) $address->id,
            'payment_method' => 'cash_on_delivery',
            'pickup_from_store' => '1',
        ])->assertStatus(201);
        $webOrder = Order::latest('id')->first();
        $this->assertEquals(30, (float) $webOrder->tax_amount);
        $this->assertEquals(230, (float) $webOrder->total_price);

        // API user: 200 + 25 shipping + 30 VAT = 255.
        [$user2, $address2] = $this->makeUserWithAddress();
        $this->userCartItem($user2, $this->makeProduct(['price' => 200]));
        $this->actingAs($user2, 'user')->postJson('/api/v1/checkout', [
            'user_address' => (string) $address2->id,
            'payment_method' => 'cash_on_delivery',
            'fixed_shipping' => '1',
        ])->assertStatus(201);
        $apiOrder = Order::latest('id')->first();
        $this->assertEquals(30, (float) $apiOrder->tax_amount);
        $this->assertEquals(255, (float) $apiOrder->total_price);
    }

    // ---------- 9. shipping null-safety ----------

    public function test_guest_checkout_without_shipping_row_returns_clean_422(): void
    {
        ShippingTypesAndPrice::query()->delete();
        $this->guestCartItem($this->makeProduct(['price' => 100]));

        $response = $this->postGuestCheckout();

        $response->assertStatus(422);
        $response->assertJsonPath('errors.shipping.0', 'طريقة الشحن غير مُعدّة في المتجر بعد.');
    }

    public function test_web_checkout_without_shipping_row_returns_clean_400(): void
    {
        ShippingTypesAndPrice::query()->delete();
        Event::fake([UserOrder::class]);
        [$user, $address] = $this->makeUserWithAddress();
        $this->userCartItem($user, $this->makeProduct(['price' => 100]));
        Cart::setCookieId('test-cookie');

        $response = $this->actingAs($user, 'web')->post(route('checkout.store'), [
            'user_address' => (string) $address->id,
            'payment_method' => 'cash_on_delivery',
            'fixed_shipping' => '1',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('الشحن', $response->json('message'));
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    // ---------- 12. order edit ----------

    public function test_user_order_edit_recomputes_from_stored_prices_and_restores_stock(): void
    {
        $owner = User::factory()->create(['password' => 'password']);
        $productA = $this->makeProduct(['price' => 100, 'quantity' => 10]);
        $productB = $this->makeProduct(['price' => 50, 'quantity' => 10]);

        $order = Order::create([
            'user_id' => $owner->id,
            'payment_method' => 'cash_on_delivery',
            'status' => OrderStatus::Pending,
            'payment_status' => 'unpaid',
            'shipping_price' => 25,
            'totalBeforeDiscount' => 250,
            'total_price' => 275,
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $productA->id, 'product_name' => 'A', 'price' => 100, 'quantity' => 2]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $productB->id, 'product_name' => 'B', 'price' => 50, 'quantity' => 1]);

        // Live prices move after the order was placed.
        $productA->update(['price' => 999]);
        $productB->update(['price' => 888]);

        $this->actingAs($owner, 'user')->postJson('/api/v1/user-orders/delete', [
            'product_id' => $productB->id,
            'order_number' => $order->number,
        ])->assertStatus(200);

        // Remaining: 2 × 100 (stored) + 25 shipping = 225 — not 2 × 999.
        $order->refresh();
        $this->assertEquals(225, (float) $order->total_price);
        $this->assertEquals(200, (float) $order->totalBeforeDiscount);
        // The removed unit goes back to stock.
        $this->assertEquals(11, $productB->fresh()->quantity);
    }

    public function test_paid_order_cannot_be_edited(): void
    {
        $owner = User::factory()->create(['password' => 'password']);
        $product = $this->makeProduct(['price' => 100, 'quantity' => 10]);

        $order = Order::create([
            'user_id' => $owner->id,
            'payment_method' => 'card_payment',
            'status' => OrderStatus::Pending,
            'payment_status' => 'paid',
            'shipping_price' => 25,
            'totalBeforeDiscount' => 100,
            'total_price' => 125,
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'A', 'price' => 100, 'quantity' => 1]);

        $this->actingAs($owner, 'user')->postJson('/api/v1/user-orders/delete', [
            'product_id' => $product->id,
            'order_number' => $order->number,
        ])->assertStatus(422);

        $this->assertDatabaseHas('order_items', ['order_id' => $order->id]);
        $this->assertEquals(10, $product->fresh()->quantity);
    }

    // ---------- 11. return restores stock ----------

    public function test_user_return_restores_stock(): void
    {
        Event::fake([UserOrder::class]);
        $owner = User::factory()->create(['password' => 'password']);
        $product = $this->makeProduct(['price' => 100, 'quantity' => 5]);

        $order = Order::create([
            'user_id' => $owner->id,
            'payment_method' => 'cash_on_delivery',
            'status' => OrderStatus::Pending,
            'payment_status' => 'unpaid',
            'shipping_price' => 0,
            'totalBeforeDiscount' => 100,
            'total_price' => 100,
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'A', 'price' => 100, 'quantity' => 2]);
        $product->update(['quantity' => 3]); // 2 units reserved by the order

        $this->actingAs($owner, 'user')->postJson('/api/v1/user-return-orders/store', [
            'return_order_id' => $order->id,
        ])->assertStatus(200);

        $this->assertEquals(5, $product->fresh()->quantity);
        $this->assertEquals(1, (int) $order->fresh()->return_order);
        // Refunds stay manual: payment_status untouched.
        $this->assertEquals('unpaid', $order->fresh()->payment_status);
    }

    // ---------- 13. payment_method validation ----------

    public function test_checkout_page_exposes_vat_rate_threshold_and_item_weight(): void
    {
        Setting::first()->update(['value_added_tax' => 15, 'free_shipping_threshold' => 500]);
        [$user, $address] = $this->makeUserWithAddress();
        $this->userCartItem($user, $this->makeProduct(['price' => 200, 'weight' => 2.5]));
        Cart::setCookieId('test-cookie');

        $this->actingAs($user, 'web')->get(route('checkout'))
            ->assertStatus(200)
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Checkout/Index')
                ->where('vatRate', 15)
                ->where('freeShippingThreshold', 500)
                ->where('cartItems.0.weight', 2.5)
            );
    }

    public function test_api_user_checkout_rejects_unknown_payment_method(): void
    {
        [$user, $address] = $this->makeUserWithAddress();
        $this->userCartItem($user, $this->makeProduct(['price' => 100]));

        $this->actingAs($user, 'user')->postJson('/api/v1/checkout', [
            'user_address' => (string) $address->id,
            'payment_method' => 'bitcoin',
            'fixed_shipping' => '1',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }
}
