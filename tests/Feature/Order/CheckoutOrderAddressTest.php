<?php

namespace Tests\Feature\Order;

use App\Models\Cart;
use App\Models\City;
use App\Models\Country;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingTypesAndPrice;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Events\UserOrder;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CheckoutOrderAddressTest extends TestCase
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
            'country_id' => $this->countryId, 'shipping_price' => 0,
        ]);

        // Explicit id 1: the app looks the row up with find(1), and
        // MySQL auto-increment is not rolled back between tests.
        ShippingTypesAndPrice::forceCreate(['id' => 1, 'add_pickup_from_store' => 1]);
    }

    private function makeUserWithAddress(?int $cityId, ?int $countryId): array
    {
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

    private function putItemInCart(User $user): void
    {
        $product = Product::factory()->create(['quantity' => 10]);
        Cart::withoutGlobalScope('cookie_id')->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'status' => 0,
            'cookie_id' => 'test-cookie',
        ]);
    }

    public function test_web_checkout_with_saved_address_copies_city_and_country(): void
    {
        Event::fake([UserOrder::class]);
        [$user, $address] = $this->makeUserWithAddress($this->cityId, $this->countryId);
        $this->putItemInCart($user);

        $response = $this->actingAs($user, 'web')->post(route('checkout.store'), [
            'user_address' => (string) $address->id,
            'terms' => '1',
            'payment_method' => 'cash_on_delivery',
            'pickup_from_store' => '1',
        ]);

        $response->assertStatus(201);
        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertDatabaseHas('order_addresses', [
            'order_id' => $order->id,
            'city_id' => $this->cityId,
            'country_id' => $this->countryId,
        ]);
    }

    public function test_web_checkout_with_saved_address_missing_city_country_falls_back_to_main_address(): void
    {
        Event::fake([UserOrder::class]);
        [$user, $main] = $this->makeUserWithAddress($this->cityId, $this->countryId);
        $second = UserAddress::create([
            'user_id' => $user->id,
            'address_title' => 'عنوان الشغل',
            'first_name' => $user->first_name,
            'family_name' => $user->family_name,
            'phone_number' => $user->phone_number,
            'address' => 'شارع الجامعة 5',
            'country_id' => null,
            'city_id' => null,
            'main_address' => false,
        ]);
        $this->putItemInCart($user);

        $response = $this->actingAs($user, 'web')->post(route('checkout.store'), [
            'user_address' => (string) $second->id,
            'terms' => '1',
            'payment_method' => 'cash_on_delivery',
            'pickup_from_store' => '1',
        ]);

        $response->assertStatus(201);
        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        // The chosen address has nulls, so the order address falls back
        // to the main address's city/country instead of storing nulls.
        $this->assertDatabaseHas('order_addresses', [
            'order_id' => $order->id,
            'city_id' => $this->cityId,
            'country_id' => $this->countryId,
        ]);
    }

    public function test_web_checkout_with_only_address_missing_city_country_still_stores_nulls(): void
    {
        Event::fake([UserOrder::class]);
        [$user, $address] = $this->makeUserWithAddress(null, null);
        $this->putItemInCart($user);

        $response = $this->actingAs($user, 'web')->post(route('checkout.store'), [
            'user_address' => (string) $address->id,
            'terms' => '1',
            'payment_method' => 'cash_on_delivery',
            'pickup_from_store' => '1',
        ]);

        $response->assertStatus(201);
        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        // No main address with city/country to fall back to, so there is
        // nothing to fill the nulls from.
        $this->assertDatabaseHas('order_addresses', [
            'order_id' => $order->id,
            'city_id' => null,
            'country_id' => null,
        ]);
    }

    public function test_api_user_checkout_with_new_flat_address_creates_order_address(): void
    {
        Event::fake([UserOrder::class]);
        [$user] = $this->makeUserWithAddress($this->cityId, $this->countryId);
        $this->putItemInCart($user);

        $response = $this->actingAs($user, 'user')->postJson('/api/v1/checkout', [
            'first_name' => 'محمد',
            'last_name' => 'جمال',
            'phone_number' => '01000000000',
            'country_id' => $this->countryId,
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
            'country_id' => $this->countryId,
        ]);
    }

    public function test_api_create_address_derives_country_from_city(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user, 'user')->postJson('/api/v1/create-address', [
            'address_title' => 'البيت',
            'first_name' => 'محمد',
            'last_name' => 'جمال',
            'address' => 'شارع التحرير 1',
            'phone_number' => '01000000000',
            'city_id' => $this->cityId,
        ]);

        $response->assertStatus(200);
        // The user had no addresses, so the old code stored country_id
        // null (it copied it from the "first" address). Now it is
        // derived from the city.
        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'city_id' => $this->cityId,
            'country_id' => $this->countryId,
        ]);
    }
}
