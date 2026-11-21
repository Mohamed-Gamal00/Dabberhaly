<?php

namespace Tests\Feature\Checkout;

use App\Events\UserOrder;
use App\Models\Cart;
use App\Models\Country;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingTypesAndPrice;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\SMSGateways\moraSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The store is Egypt-only: in every customer-facing form that asks
 * for country + city, the country must be Egypt automatically — the
 * customer never picks a country, they only pick the governorate
 * (city). These tests post each flow WITHOUT country_id and assert
 * Egypt (the country row with code 'EG') ends up stored.
 */
class CountryDefaultsToEgyptTest extends TestCase
{
    use RefreshDatabase;

    private int $egyptId;
    private int $cityId;
    private string $guestCookie = 'egypt-guest-cookie';

    protected function setUp(): void
    {
        parent::setUp();

        $this->egyptId = Country::create([
            'name_ar' => 'مصر',
            'name_en' => 'Egypt',
            'code' => 'EG',
            'phone_code' => '+20',
            'status' => 'used',
        ])->id;
        $this->cityId = DB::table('cities')->insertGetId([
            'name_ar' => 'القاهرة', 'name_en' => 'Cairo', 'status' => 'used',
            'country_id' => $this->egyptId, 'shipping_price' => 25,
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

    private function makeUser(): User
    {
        return User::factory()->create(['password' => 'password']);
    }

    private function putItemInCart(?User $user = null, ?string $cookieId = null): void
    {
        $product = Product::factory()->create(['quantity' => 10]);
        Cart::withoutGlobalScope('cookie_id')->create([
            'user_id' => $user?->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'status' => 0,
            'cookie_id' => $cookieId ?? 'test-cookie',
        ]);
    }

    // (1) Web register completes WITHOUT sending country_id, and the
    // created user + main address have Egypt as the country.
    public function test_web_register_without_country_id_creates_user_with_egypt_main_address(): void
    {
        // The register step itself must not require a country: posting
        // the form without country_id must produce no country_id error.
        // (This sandbox has no DNS, so the email:rfc,dns rule fails
        // here — the assertion is only that country is not among the
        // errors, i.e. the missing country is accepted.)
        $response = $this->post(route('auth.register'), [
            'first_name' => 'Mohamed',
            'family_name' => 'Gamal',
            'email' => 'web-reg@example.com',
            'phone_number' => '01054125230',
            'address' => '12 Tahrir St, Dokki, Giza',
            'city_id' => $this->cityId,
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ]);

        $messages = session('errors')?->getBag('default')->messages() ?? [];
        $this->assertArrayNotHasKey('country_id', $messages);

        // The verify step creates the user + main address from the
        // pending registration stored in the session (seeded here the
        // way register() stores it — without a country_id — as in
        // SecurityBatch2Test).
        $this->withSession([
            'pending_registration' => [
                'first_name' => 'Mohamed',
                'family_name' => 'Gamal',
                'phone_number' => '01054125230',
                'email' => 'web-reg@example.com',
                'password' => Hash::make('NewPass123'),
                'address' => '12 Tahrir St, Dokki, Giza',
                'city_id' => $this->cityId,
            ],
            'pending_otp' => [
                'code' => '1234',
                'expires_at' => now()->addMinutes(10),
            ],
        ])->post('/verify', ['code' => '1234'])
            ->assertRedirect(route('home'));

        $user = User::where('phone_number', '01054125230')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'main_address' => true,
            'city_id' => $this->cityId,
            'country_id' => $this->egyptId,
        ]);
    }

    // (2) Web checkout with a new address and NO country_id stores
    // Egypt on the order address AND on the saved address.
    public function test_web_checkout_new_address_without_country_id_stores_egypt(): void
    {
        Event::fake([UserOrder::class]);
        $user = $this->makeUser();
        $this->putItemInCart($user);

        $response = $this->actingAs($user, 'web')->post(route('checkout.store'), [
            'addr' => ['shipping' => [
                'first_name' => 'محمد',
                'last_name' => 'تجريبي',
                'phone_number' => '01000000000',
                'city_id' => $this->cityId,
                'address' => 'شارع التحرير 1',
            ]],
            'payment_method' => 'cash_on_delivery',
            'pickup_from_store' => '1',
        ]);

        $response->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertDatabaseHas('order_addresses', [
            'order_id' => $order->id,
            'city_id' => $this->cityId,
            'country_id' => $this->egyptId,
        ]);
        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'main_address' => false,
            'city_id' => $this->cityId,
            'country_id' => $this->egyptId,
        ]);
    }

    // (3) API user checkout without country_id stores Egypt on the
    // order address.
    public function test_api_user_checkout_without_country_id_stores_egypt(): void
    {
        Event::fake([UserOrder::class]);
        // No addresses at all on this user, so nothing can mask the
        // default via the main-address fallback.
        $user = $this->makeUser();
        $this->putItemInCart($user);

        $response = $this->actingAs($user, 'user')->postJson('/api/v1/checkout', [
            'first_name' => 'محمد',
            'last_name' => 'جمال',
            'phone_number' => '01000000000',
            'city_id' => $this->cityId,
            'address' => 'شارع التحرير 1',
            'terms' => true,
            'payment_method' => 'cash_on_delivery',
            'pickup_from_store' => '1',
        ]);

        $response->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertDatabaseHas('order_addresses', [
            'order_id' => $order->id,
            'city_id' => $this->cityId,
            'country_id' => $this->egyptId,
        ]);
    }

    // (4) API guest checkout without country_id stores Egypt on the
    // order address.
    public function test_api_guest_checkout_without_country_id_stores_egypt(): void
    {
        Event::fake([UserOrder::class]);
        $this->putItemInCart(null, $this->guestCookie);

        $response = $this->withHeaders(['guest-id' => $this->guestCookie])
            ->postJson('/api/v1/guest-checkout', [
                'terms' => true,
                'first_name' => 'ضيف',
                'last_name' => 'تجريبي',
                'phone_number' => '01000000000',
                'city_id' => $this->cityId,
                'address' => 'شارع التحرير 1',
                'payment_method' => 'cash_on_delivery',
                'fixed_shipping' => 1,
            ]);

        $response->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertDatabaseHas('order_addresses', [
            'order_id' => $order->id,
            'city_id' => $this->cityId,
            'country_id' => $this->egyptId,
        ]);
    }

    // (5) API register without country_id stores Egypt on the main
    // address.
    public function test_api_register_without_country_id_stores_egypt_on_main_address(): void
    {
        // setUp already created the settings row; just fill the name
        // the verification SMS uses (same pattern as
        // EgyptianMobileValidationTest).
        Setting::first()->update(['website_name' => 'Dabberhaly']);

        $this->mock(moraSms::class, function ($mock) {
            $mock->shouldReceive('sendSms')->once()->andReturn(true);
        });

        $response = $this->postJson('/api/v1/register', [
            'first_name' => 'Ahmed',
            'family_name' => 'Ali',
            'email' => 'api-reg-egypt@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone_number' => '01512345678',
            'address' => 'شارع التحرير 1',
            'city_id' => $this->cityId,
        ]);

        $response->assertOk();

        $user = User::where('phone_number', '01512345678')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'main_address' => true,
            'city_id' => $this->cityId,
            'country_id' => $this->egyptId,
        ]);
    }
}
