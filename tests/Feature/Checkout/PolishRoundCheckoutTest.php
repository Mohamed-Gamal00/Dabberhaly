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
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Tests for the polish round: the soft-deleted-product error must
 * reach the customer on the web + API-user checkout paths (#3, M6
 * caveat) and the dead verify_to_update routes must be gone (#4).
 */
class PolishRoundCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private int $countryId;
    private int $cityId;
    private int $categoryId;

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

        Setting::create(['value_added_tax' => 0, 'free_shipping_threshold' => null]);
    }

    protected function tearDown(): void
    {
        Cart::setCookieId(null);

        parent::tearDown();
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'price' => 100, 'discount_price' => null, 'quantity' => 50, 'weight' => 1,
            'category_id' => $this->categoryId,
        ], $overrides));
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

    private function userCartItem(User $user, Product $product, int $quantity = 1): void
    {
        Cart::withoutGlobalScope('cookie_id')->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'status' => 0,
            'cookie_id' => 'test-cookie',
        ]);
    }

    // ---------- #3: M6 caveat — clean error on web + API-user checkout ----------

    public function test_web_checkout_with_soft_deleted_product_returns_clean_error(): void
    {
        Event::fake([UserOrder::class]);
        [$user, $address] = $this->makeUserWithAddress();
        $product = $this->makeProduct();
        $this->userCartItem($user, $product);
        $product->delete();
        Cart::setCookieId('test-cookie');

        // Pickup shipping skips the pre-check totals, so the error
        // surfaces inside the order-creation transaction — it used to
        // come back as the generic "order creation failed".
        $response = $this->actingAs($user, 'web')->post(route('checkout.store'), [
            'user_address' => (string) $address->id,
            'payment_method' => 'cash_on_delivery',
            'pickup_from_store' => '1',
        ]);

        $response->assertStatus(400);
        $this->assertSame('المنتج غير متاح', $response->json('message'));
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    public function test_web_checkout_with_soft_deleted_product_and_fixed_shipping_returns_clean_error(): void
    {
        Event::fake([UserOrder::class]);
        [$user, $address] = $this->makeUserWithAddress();
        $product = $this->makeProduct();
        $this->userCartItem($user, $product);
        $product->delete();
        Cart::setCookieId('test-cookie');

        $response = $this->actingAs($user, 'web')->post(route('checkout.store'), [
            'user_address' => (string) $address->id,
            'payment_method' => 'cash_on_delivery',
            'fixed_shipping' => '1',
        ]);

        $response->assertStatus(400);
        $this->assertSame('المنتج غير متاح', $response->json('message'));
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    public function test_api_user_checkout_with_soft_deleted_product_returns_clean_error(): void
    {
        Event::fake([UserOrder::class]);
        [$user, $address] = $this->makeUserWithAddress();
        $product = $this->makeProduct();
        $this->userCartItem($user, $product);
        $product->delete();

        $response = $this->actingAs($user, 'user')->postJson('/api/v1/checkout', [
            'user_address' => (string) $address->id,
            'payment_method' => 'cash_on_delivery',
            'pickup_from_store' => '1',
        ]);

        $response->assertStatus(400);
        $this->assertSame('المنتج غير متاح', $response->json('message'));
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    // ---------- #4: dead verify_to_update routes are gone ----------

    public function test_dead_verify_to_update_routes_return_404(): void
    {
        [$user] = $this->makeUserWithAddress();

        $this->actingAs($user, 'web')
            ->post('/user_normal_info/verify_to_update')
            ->assertNotFound();

        $this->actingAs($user, 'web')
            ->post('/user_normal_info/resendVerifyCode_to_update')
            ->assertNotFound();
    }
}
