<?php

namespace Tests\Feature\Cart;

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

class FreeShippingThresholdTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Cart::setCookieId(null);

        parent::tearDown();
    }

    private function makeSetting($threshold): Setting
    {
        return Setting::create([
            'website_name' => 'Test Store',
            'value_added_tax' => 0,
            'free_shipping_threshold' => $threshold,
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

    private function putItemInCart(User $user, int $price = 200): void
    {
        $product = Product::factory()->create(['price' => $price, 'quantity' => 10]);
        Cart::withoutGlobalScope('cookie_id')->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'status' => 0,
            'cookie_id' => 'test-cookie',
        ]);
        Cart::setCookieId('test-cookie');
    }

    public function test_cart_response_returns_default_threshold_when_no_setting_exists(): void
    {
        $response = $this->getJson('/cart');

        $response->assertStatus(200);
        $response->assertJsonPath('free_shipping_threshold', 500);
    }

    public function test_cart_response_returns_threshold_from_settings(): void
    {
        Setting::create([
            'website_name' => 'Test Store',
            'free_shipping_threshold' => 300,
        ]);

        $response = $this->getJson('/cart');

        $response->assertStatus(200);
        $response->assertJsonPath('free_shipping_threshold', 300);
    }

    public function test_admin_can_update_threshold_from_settings_page(): void
    {
        $setting = Setting::create([
            'website_name' => 'Test Store',
            'free_shipping_threshold' => 500,
        ]);

        $admin = Admin::forceCreate([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'super_admin' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->put(
            route('settings.update', ['id' => $setting->id]),
            [
                'website_name' => 'Test Store',
                'free_shipping_threshold' => 250,
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('settings', [
            'id' => $setting->id,
            'free_shipping_threshold' => 250,
        ]);
    }

    public function test_cart_response_returns_zero_when_threshold_is_zero(): void
    {
        $this->makeSetting(0);

        $this->getJson('/cart')
            ->assertStatus(200)
            ->assertJsonPath('free_shipping_threshold', 0);
    }

    public function test_cart_response_returns_null_when_threshold_is_null(): void
    {
        $this->makeSetting(null);

        $this->getJson('/cart')
            ->assertStatus(200)
            ->assertJsonPath('free_shipping_threshold', null);
    }

    public function test_web_checkout_charges_shipping_when_threshold_is_zero(): void
    {
        Event::fake([UserOrder::class]);
        $this->makeSetting(0);
        [$user, $address] = $this->makeUserWithAddress();
        $this->putItemInCart($user);

        $this->actingAs($user, 'web')->post(route('checkout.store'), [
            'user_address' => (string) $address->id,
            'payment_method' => 'cash_on_delivery',
            'fixed_shipping' => '1',
        ])->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertEquals(25, (float) $order->shipping_price);
        $this->assertEquals(225, (float) $order->total_price);
    }

    public function test_web_checkout_charges_shipping_when_threshold_is_null(): void
    {
        Event::fake([UserOrder::class]);
        $this->makeSetting(null);
        [$user, $address] = $this->makeUserWithAddress();
        $this->putItemInCart($user);

        $this->actingAs($user, 'web')->post(route('checkout.store'), [
            'user_address' => (string) $address->id,
            'payment_method' => 'cash_on_delivery',
            'fixed_shipping' => '1',
        ])->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertEquals(25, (float) $order->shipping_price);
        $this->assertEquals(225, (float) $order->total_price);
    }
}
