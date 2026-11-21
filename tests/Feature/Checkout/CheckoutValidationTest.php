<?php

namespace Tests\Feature\Checkout;

use App\Events\UserOrder;
use App\Models\Cart;
use App\Models\MainCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingTypesAndPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Validation gaps fixed in the web checkout + API guest checkout:
 * - phone_number used `numeric` / no format rule, rejecting valid
 *   phones starting with `+` and accepting gibberish — now the
 *   shared Egyptian-mobile rule (App\Rules\EgyptianMobileNumber).
 * - guest_email had no max; address allowed 500 chars but the
 *   user_addresses column is varchar(255).
 * - discount_code had no max.
 */
class CheckoutValidationTest extends TestCase
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

        Setting::create(['value_added_tax' => 0, 'free_shipping_threshold' => null]);
    }

    protected function tearDown(): void
    {
        Cart::setCookieId(null);

        parent::tearDown();
    }

    // ---------- web checkout helpers ----------

    private function putGuestItemInCart(): void
    {
        $categoryId = MainCategory::create(['name' => 'Test Category'])->id;
        $product = Product::factory()->create([
            'price' => 200, 'discount_price' => null, 'quantity' => 50,
            'weight' => 1, 'category_id' => $categoryId,
        ]);
        Cart::withoutGlobalScope('cookie_id')->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'status' => 0,
            'cookie_id' => $this->guestCookie,
        ]);
    }

    private function webGuestPayload(array $overrides = []): array
    {
        return array_merge([
            'addr' => ['billing' => [
                'first_name' => 'محمد',
                'last_name' => 'جمال',
                'phone_number' => '01000000000',
                'country_id' => $this->countryId,
                'city_id' => $this->cityId,
                'address' => 'شارع التحرير 1',
            ]],
            'guest_email' => 'guest@example.com',
            'payment_method' => 'cash_on_delivery',
            'pickup_from_store' => '1',
        ], $overrides);
    }

    private function postWebGuestCheckout(array $overrides = [])
    {
        Event::fake([UserOrder::class]);
        Cart::setCookieId($this->guestCookie);
        $this->putGuestItemInCart();

        return $this->post(route('checkout.store'), $this->webGuestPayload($overrides));
    }

    // ---------- web checkout: phone ----------

    public function test_web_guest_checkout_rejects_non_numeric_phone(): void
    {
        $response = $this->postWebGuestCheckout([
            'addr' => ['billing' => [
                'first_name' => 'محمد',
                'last_name' => 'جمال',
                'phone_number' => 'not-a-phone',
                'country_id' => $this->countryId,
                'city_id' => $this->cityId,
                'address' => 'شارع التحرير 1',
            ]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('addr.billing.phone_number');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_web_guest_checkout_accepts_phone_with_country_code_plus(): void
    {
        $response = $this->postWebGuestCheckout([
            'addr' => ['billing' => [
                'first_name' => 'محمد',
                'last_name' => 'جمال',
                'phone_number' => '+201000000000',
                'country_id' => $this->countryId,
                'city_id' => $this->cityId,
                'address' => 'شارع التحرير 1',
            ]],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_web_guest_checkout_accepts_phone_with_spaces_and_dashes(): void
    {
        $response = $this->postWebGuestCheckout([
            'addr' => ['billing' => [
                'first_name' => 'محمد',
                'last_name' => 'جمال',
                'phone_number' => '+20 100 000 0000',
                'country_id' => $this->countryId,
                'city_id' => $this->cityId,
                'address' => 'شارع التحرير 1',
            ]],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_web_guest_checkout_rejects_too_short_phone(): void
    {
        $response = $this->postWebGuestCheckout([
            'addr' => ['billing' => [
                'first_name' => 'محمد',
                'last_name' => 'جمال',
                'phone_number' => '12345',
                'country_id' => $this->countryId,
                'city_id' => $this->cityId,
                'address' => 'شارع التحرير 1',
            ]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('addr.billing.phone_number');
    }

    // ---------- web checkout: email / address / discount ----------

    public function test_web_guest_checkout_rejects_overlong_guest_email(): void
    {
        $response = $this->postWebGuestCheckout([
            'guest_email' => str_repeat('a', 250) . '@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('guest_email');
    }

    public function test_web_guest_checkout_rejects_address_longer_than_column(): void
    {
        $response = $this->postWebGuestCheckout([
            'addr' => ['billing' => [
                'first_name' => 'محمد',
                'last_name' => 'جمال',
                'phone_number' => '01000000000',
                'country_id' => $this->countryId,
                'city_id' => $this->cityId,
                'address' => str_repeat('شارع ', 60),
            ]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('addr.billing.address');
    }

    public function test_web_apply_discount_rejects_overlong_code(): void
    {
        $response = $this->postJson(route('checkout.discount'), [
            'discount_code' => str_repeat('X', 300),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('discount_code');
    }

    // ---------- API guest checkout: phone ----------

    private function apiGuestPayload(array $overrides = []): array
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

    private function postApiGuestCheckout(array $overrides = [])
    {
        Event::fake([UserOrder::class]);
        $this->putGuestItemInCart();

        return $this->withHeaders(['guest-id' => $this->guestCookie])
            ->postJson('/api/v1/guest-checkout', $this->apiGuestPayload($overrides));
    }

    public function test_api_guest_checkout_rejects_non_numeric_phone(): void
    {
        $response = $this->postApiGuestCheckout(['phone_number' => 'not-a-phone']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('phone_number');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_api_guest_checkout_accepts_phone_with_country_code_plus(): void
    {
        $response = $this->postApiGuestCheckout(['phone_number' => '+201000000000']);

        $response->assertStatus(201);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_api_guest_checkout_rejects_unknown_country(): void
    {
        $response = $this->postApiGuestCheckout(['country_id' => 999999]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('country_id');
    }

    private function putUserItemInCart(User $user): void
    {
        $categoryId = MainCategory::create(['name' => 'Test Category'])->id;
        $product = Product::factory()->create([
            'price' => 200, 'discount_price' => null, 'quantity' => 50,
            'weight' => 1, 'category_id' => $categoryId,
        ]);
        Cart::withoutGlobalScope('cookie_id')->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'status' => 0,
            'cookie_id' => 'test-cookie',
        ]);
    }

    private function apiUserPayload(array $overrides = []): array
    {
        return array_merge([
            'terms' => true,
            'first_name' => 'محمد',
            'last_name' => 'جمال',
            'phone_number' => '01000000000',
            'country_id' => $this->countryId,
            'city_id' => $this->cityId,
            'address' => 'شارع التحرير 1',
            'payment_method' => 'cash_on_delivery',
            'fixed_shipping' => 1,
        ], $overrides);
    }

    private function postApiUserCheckout(User $user, array $overrides = [])
    {
        Event::fake([UserOrder::class]);
        $this->putUserItemInCart($user);

        return $this->actingAs($user, 'user')
            ->postJson('/api/v1/checkout', $this->apiUserPayload($overrides));
    }

    // ---------- API user checkout: phone ----------

    public function test_api_user_checkout_rejects_non_numeric_phone(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->postApiUserCheckout($user, ['phone_number' => 'not-a-phone']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('phone_number');
    }

    public function test_api_user_checkout_accepts_phone_with_country_code_plus(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->postApiUserCheckout($user, ['phone_number' => '+201000000000']);

        $response->assertStatus(201);
        $this->assertDatabaseCount('orders', 1);
    }
}
