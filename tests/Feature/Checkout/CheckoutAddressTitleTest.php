<?php

namespace Tests\Feature\Checkout;

use App\Events\UserOrder;
use App\Models\Cart;
use App\Models\MainCategory;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingTypesAndPrice;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CheckoutAddressTitleTest extends TestCase
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

    private function makeAddress(User $user, string $title): UserAddress
    {
        return UserAddress::create([
            'user_id' => $user->id,
            'address_title' => $title,
            'first_name' => 'محمد',
            'family_name' => 'تجريبي',
            'phone_number' => '01000000000',
            'country_id' => $this->countryId,
            'city_id' => $this->cityId,
            'address' => 'شارع التحرير 1',
            'main_address' => false,
        ]);
    }

    private function putItemInCart(User $user): void
    {
        $product = Product::factory()->create([
            'quantity' => 10,
            'category_id' => MainCategory::create(['name' => 'Test Category'])->id,
        ]);
        Cart::withoutGlobalScope('cookie_id')->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'status' => 0,
            'cookie_id' => 'test-cookie',
        ]);
    }

    public function test_system_titles_localize_both_ways_on_read(): void
    {
        $user = $this->makeUser();
        $arStored = $this->makeAddress($user, 'عنوان جديد');
        $enStored = $this->makeAddress($user, 'New Address');
        $typed = $this->makeAddress($user, 'بيت العيلة');
        $main = $this->makeAddress($user, 'العنوان الأساسي');

        app()->setLocale('en');
        $this->assertSame('New Address', $arStored->fresh()->address_title);
        $this->assertSame('New Address', $enStored->fresh()->address_title);
        $this->assertSame('Main Address', $main->fresh()->address_title);
        $this->assertSame('بيت العيلة', $typed->fresh()->address_title);

        app()->setLocale('ar');
        $this->assertSame('عنوان جديد', $arStored->fresh()->address_title);
        $this->assertSame('عنوان جديد', $enStored->fresh()->address_title);
        $this->assertSame('العنوان الأساسي', $main->fresh()->address_title);
        $this->assertSame('بيت العيلة', $typed->fresh()->address_title);
    }

    public function test_existing_arabic_title_displays_english_on_english_site(): void
    {
        $user = $this->makeUser();
        $this->makeAddress($user, 'عنوان جديد');
        $this->putItemInCart($user);

        $this->withSession(['locale' => 'en'])
            ->actingAs($user, 'web')
            ->get(route('checkout'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('userAddresses.0.address_title', 'New Address'));
    }

    public function test_checkout_creates_address_with_english_title_on_english_site(): void
    {
        Event::fake([UserOrder::class]);
        $user = $this->makeUser();
        $this->putItemInCart($user);

        $response = $this->withSession(['locale' => 'en'])
            ->actingAs($user, 'web')
            ->post(route('checkout.store'), [
                'addr' => ['shipping' => [
                    'first_name' => 'محمد',
                    'last_name' => 'تجريبي',
                    'phone_number' => '01000000000',
                    'country_id' => $this->countryId,
                    'city_id' => $this->cityId,
                    'address' => 'شارع التحرير 1',
                ]],
                'payment_method' => 'cash_on_delivery',
                'pickup_from_store' => '1',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'address_title' => 'New Address',
        ]);
    }
}
