<?php

namespace Tests\Feature\Order;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingTypesAndPrice;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Events\UserOrder;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CheckoutOrderSuccessTest extends TestCase
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

    protected function tearDown(): void
    {
        Cart::setCookieId(null);

        parent::tearDown();
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

    private function putItemInCart(?User $user = null): void
    {
        $product = Product::factory()->create(['quantity' => 10]);
        Cart::withoutGlobalScope('cookie_id')->create([
            'user_id' => $user?->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'status' => 0,
            'cookie_id' => 'test-cookie',
        ]);
    }

    public function test_logged_in_cash_on_delivery_redirects_to_profile_orders_with_success_flash(): void
    {
        Event::fake([UserOrder::class]);
        [$user, $address] = $this->makeUserWithAddress();
        $this->putItemInCart($user);

        $response = $this->actingAs($user, 'web')->post(route('checkout.store'), [
            'user_address' => (string) $address->id,
            'terms' => '1',
            'payment_method' => 'cash_on_delivery',
            'pickup_from_store' => '1',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('redirect', route('user.profile', ['tab' => 'orders']));

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $response->assertSessionHas('success');
        $this->assertStringContainsString((string) $order->number, session('success'));
    }

    public function test_guest_cash_on_delivery_redirects_to_success_page(): void
    {
        Event::fake([UserOrder::class]);
        Cart::setCookieId('test-cookie');
        $this->putItemInCart();

        $response = $this->post(route('checkout.store'), [
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
        ]);

        $response->assertStatus(201);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $response->assertJsonPath(
            'redirect',
            route('checkout.success', ['order_number' => $order->number])
        );
    }

    public function test_guest_success_page_shows_order(): void
    {
        Event::fake([UserOrder::class]);
        Cart::setCookieId('test-cookie');
        $this->putItemInCart();

        $this->post(route('checkout.store'), [
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
        ]);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);

        $response = $this->get(route('checkout.success', ['order_number' => $order->number]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Checkout/Success')
            ->where('order.number', (string) $order->number)
            ->where('order.is_guest', true)
        );
    }

    public function test_success_page_without_order_redirects_home(): void
    {
        $response = $this->get(route('checkout.success'));

        $response->assertRedirect(route('home'));
    }

    public function test_card_payment_redirect_unchanged(): void
    {
        Event::fake([UserOrder::class]);
        [$user, $address] = $this->makeUserWithAddress();
        $this->putItemInCart($user);

        $response = $this->actingAs($user, 'web')->post(route('checkout.store'), [
            'user_address' => (string) $address->id,
            'payment_method' => 'card_payment',
            'pickup_from_store' => '1',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath(
            'redirect',
            route('user.payment', ['order_number' => Order::latest('id')->first()->number])
        );
    }
}
