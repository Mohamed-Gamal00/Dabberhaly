<?php

namespace Tests\Feature\Settings;

use App\Events\UserOrder;
use App\Models\Admin;
use App\Models\Cart;
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

class PaymentMethodsSettingsTest extends TestCase
{
    use RefreshDatabase;

    private int $countryId;
    private int $cityId;

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
        ]);

        Setting::create([
            'website_name' => 'Test Store',
            'value_added_tax' => 0,
            'free_shipping_threshold' => null,
            'payment_cod_enabled' => true,
            'payment_card_enabled' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Cart::setCookieId(null);

        parent::tearDown();
    }

    private function makeSuperAdmin(): Admin
    {
        return Admin::forceCreate([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'super_admin' => true,
        ]);
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

    private function userCartItem(User $user, Product $product): void
    {
        Cart::withoutGlobalScope('cookie_id')->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'status' => 0,
            'cookie_id' => 'test-cookie',
        ]);
    }

    private function disableCard(): void
    {
        Setting::first()->update(['payment_card_enabled' => false]);
    }

    public function test_both_methods_enabled_when_no_settings_row_exists(): void
    {
        Setting::query()->delete();

        $this->assertEquals(
            ['cash_on_delivery', 'card_payment'],
            Setting::enabledPaymentMethods()
        );
    }

    public function test_admin_can_disable_card_from_settings_page(): void
    {
        $setting = Setting::first();

        $response = $this->actingAs($this->makeSuperAdmin(), 'admin')->put(
            route('settings.update', ['id' => $setting->id]),
            [
                'website_name' => 'Test Store',
                'payment_cod_enabled' => 1,
                'payment_card_enabled' => 0,
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('settings', [
            'id' => $setting->id,
            'payment_cod_enabled' => 1,
            'payment_card_enabled' => 0,
        ]);
        $this->assertEquals(['cash_on_delivery'], Setting::enabledPaymentMethods());
    }

    public function test_admin_cannot_disable_both_payment_methods(): void
    {
        $setting = Setting::first();

        $response = $this->actingAs($this->makeSuperAdmin(), 'admin')->put(
            route('settings.update', ['id' => $setting->id]),
            [
                'website_name' => 'Test Store',
                'payment_cod_enabled' => 0,
                'payment_card_enabled' => 0,
            ]
        );

        $response->assertSessionHasErrors('payment_card_enabled');
        $this->assertDatabaseHas('settings', [
            'id' => $setting->id,
            'payment_cod_enabled' => 1,
            'payment_card_enabled' => 1,
        ]);
    }

    public function test_checkout_page_lists_only_enabled_methods(): void
    {
        $this->disableCard();
        [$user, $address] = $this->makeUserWithAddress();
        $this->userCartItem($user, Product::factory()->create(['quantity' => 10]));
        Cart::setCookieId('test-cookie');

        $this->actingAs($user, 'web')->get(route('checkout'))
            ->assertStatus(200)
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Checkout/Index')
                ->where('paymentMethods', ['cash_on_delivery'])
            );
    }

    public function test_web_checkout_rejects_disabled_card_payment(): void
    {
        Event::fake([UserOrder::class]);
        $this->disableCard();
        [$user, $address] = $this->makeUserWithAddress();
        $this->userCartItem($user, Product::factory()->create(['quantity' => 10]));

        $this->actingAs($user, 'web')->post(route('checkout.store'), [
            'user_address' => (string) $address->id,
            'payment_method' => 'card_payment',
            'pickup_from_store' => '1',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');

        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    public function test_web_checkout_still_accepts_cod_when_card_disabled(): void
    {
        Event::fake([UserOrder::class]);
        $this->disableCard();
        [$user, $address] = $this->makeUserWithAddress();
        $this->userCartItem($user, Product::factory()->create(['quantity' => 10]));

        $this->actingAs($user, 'web')->post(route('checkout.store'), [
            'user_address' => (string) $address->id,
            'payment_method' => 'cash_on_delivery',
            'pickup_from_store' => '1',
        ])->assertStatus(201);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'payment_method' => 'cash_on_delivery',
        ]);
    }

    public function test_api_user_checkout_rejects_disabled_card_payment(): void
    {
        $this->disableCard();
        [$user, $address] = $this->makeUserWithAddress();
        $this->userCartItem($user, Product::factory()->create(['quantity' => 10]));

        $this->actingAs($user, 'user')->postJson('/api/v1/checkout', [
            'user_address' => (string) $address->id,
            'payment_method' => 'card_payment',
            'fixed_shipping' => '1',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');

        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    public function test_guest_checkout_rejects_disabled_card_payment(): void
    {
        $this->disableCard();
        $product = Product::factory()->create(['quantity' => 10]);
        Cart::withoutGlobalScope('cookie_id')->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'status' => 0,
            'cookie_id' => 'guest-cookie-123',
        ]);

        $this->withHeaders(['guest-id' => 'guest-cookie-123'])
            ->postJson('/api/v1/guest-checkout', [
                'terms' => true,
                'first_name' => 'ضيف',
                'last_name' => 'تجريبي',
                'phone_number' => '01000000000',
                'country_id' => $this->countryId,
                'city_id' => $this->cityId,
                'address' => 'شارع التحرير 1',
                'payment_method' => 'card_payment',
                'fixed_shipping' => 1,
            ])->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');

        $this->assertEquals(0, Order::count());
    }
}
