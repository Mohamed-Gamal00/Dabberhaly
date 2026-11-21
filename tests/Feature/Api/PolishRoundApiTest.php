<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\DiscountCode;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\User_verfication;
use App\Services\SMSGateways\moraSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Tests for the polish round: CartResource null-safety (#1),
 * registration country derived from the city (#7), the guest cart
 * stock cap on update (#8), the API remove-discount endpoints (#9)
 * and the CheckUserVerification resend guard (#14).
 */
class PolishRoundApiTest extends TestCase
{
    use RefreshDatabase;

    private int $countryId;
    private int $cityId;
    private string $guestCookie = 'guest-cookie-polish';

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
    }

    protected function tearDown(): void
    {
        Cart::setCookieId(null);

        parent::tearDown();
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'price' => 200, 'discount_price' => null, 'quantity' => 50, 'weight' => 1,
            'category_id' => null,
        ], $overrides));
    }

    private function guestCartItem(Product $product, int $quantity = 1): Cart
    {
        return Cart::withoutGlobalScope('cookie_id')->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'status' => 0,
            'cookie_id' => $this->guestCookie,
        ]);
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

    // ---------- #1: CartResource with a category-less product ----------

    public function test_guest_cart_returns_200_when_product_has_no_category(): void
    {
        $product = $this->makeProduct(['name' => 'منتج بلا تصنيف']);
        $this->guestCartItem($product);

        $response = $this->withHeaders(['guest-id' => $this->guestCookie, 'Accept-Language' => 'ar'])
            ->getJson('/api/v1/guest-cart/');

        $response->assertStatus(200);
        // A ResourceCollection nests its rows under a `data` key when
        // embedded in another payload — unwrap either shape.
        $items = $response->json('data.items');
        $items = $items['data'] ?? $items;
        $this->assertSame('منتج بلا تصنيف', $items[0]['name']);
        $this->assertNull($items[0]['category']);
    }

    public function test_user_cart_returns_200_when_product_has_no_category(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $product = $this->makeProduct(['name' => 'منتج بلا تصنيف']);
        Cart::withoutGlobalScope('cookie_id')->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'status' => 0,
            'cookie_id' => 'test-cookie',
        ]);

        $response = $this->actingAs($user, 'user')
            ->withHeaders(['Accept-Language' => 'ar'])
            ->getJson('/api/v1/cart/');

        $response->assertStatus(200);
        // A ResourceCollection nests its rows under a `data` key when
        // embedded in another payload — unwrap either shape.
        $items = $response->json('data.items');
        $items = $items['data'] ?? $items;
        $this->assertSame('منتج بلا تصنيف', $items[0]['name']);
        $this->assertNull($items[0]['category']);
    }

    // ---------- #7: registration derives the country from the city ----------

    public function test_api_registration_stores_the_citys_country(): void
    {
        Setting::create(['website_name' => 'Dabberhaly']);
        RateLimiter::clear('api-register:127.0.0.1');

        $this->mock(moraSms::class, function ($mock) {
            $mock->shouldReceive('sendSms')->atLeast()->once()->andReturn(true);
        });

        $this->postJson('/api/v1/register', [
            'first_name' => 'Ahmed',
            'family_name' => 'Ali',
            'email' => 'polish-reg@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone_number' => '01000000991',
            'address' => 'شارع التحرير 1',
            'city_id' => $this->cityId,
        ])->assertOk();

        $user = User::where('email', 'polish-reg@example.com')->firstOrFail();
        $address = $user->addresses()->where('main_address', true)->firstOrFail();

        // The city belongs to Egypt (not the old hardcoded 178).
        $this->assertSame($this->countryId, (int) $address->country_id);
        $this->assertNotSame(178, (int) $address->country_id);
        $this->assertSame($this->cityId, (int) $address->city_id);
    }

    // ---------- #8: guest cart update respects stock ----------

    public function test_guest_cart_update_above_stock_is_rejected_like_store(): void
    {
        $product = $this->makeProduct(['quantity' => 3]);
        $item = $this->guestCartItem($product, 1);

        $response = $this->withHeaders(['guest-id' => $this->guestCookie])
            ->postJson("/api/v1/guest-cart/update/{$item->id}", ['quantity' => 10]);

        $response->assertStatus(400);
        $this->assertStringContainsString('not available', $response->json('message'));
        $this->assertSame(1, (int) $item->fresh()->quantity);
    }

    public function test_guest_cart_update_within_stock_succeeds(): void
    {
        $product = $this->makeProduct(['quantity' => 3]);
        $item = $this->guestCartItem($product, 1);

        $this->withHeaders(['guest-id' => $this->guestCookie])
            ->postJson("/api/v1/guest-cart/update/{$item->id}", ['quantity' => 3])
            ->assertStatus(200);

        $this->assertSame(3, (int) $item->fresh()->quantity);
    }

    // ---------- #9: API remove-discount ----------

    public function test_api_user_remove_discount_restores_full_price(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $this->makeDiscountCode('FIX50', 50, 'fixed');
        $product = $this->makeProduct(['price' => 200]);
        Cart::withoutGlobalScope('cookie_id')->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'status' => 0,
            'cookie_id' => 'test-cookie',
        ]);

        $this->actingAs($user, 'user')
            ->postJson('/api/v1/check-discount', ['discount_code' => 'FIX50'])
            ->assertStatus(200);
        $this->assertEquals(150, (float) Cart::withoutGlobalScope('cookie_id')
            ->where('user_id', $user->id)->where('status', 0)->first()->discounted_price);

        $response = $this->actingAs($user, 'user')->postJson('/api/v1/remove-discount');
        $response->assertStatus(200);
        // json_encode((float) 200) is `200`, so the JSON value is int.
        $response->assertJsonPath('data.new_total', 200);

        $item = Cart::withoutGlobalScope('cookie_id')
            ->where('user_id', $user->id)->where('status', 0)->first();
        $this->assertNull($item->discounted_price);
        $this->assertDatabaseMissing('applied_discount_codes', ['cookie_id' => 'test-cookie']);

        $this->actingAs($user, 'user')->getJson('/api/v1/cart/')
            ->assertStatus(200)
            ->assertJsonPath('data.total_price', 200);
    }

    public function test_api_guest_remove_discount_restores_full_price(): void
    {
        $this->makeDiscountCode('FIX50', 50, 'fixed');
        $product = $this->makeProduct(['price' => 200]);
        $this->guestCartItem($product);

        $this->withHeaders(['guest-id' => $this->guestCookie])
            ->postJson('/api/v1/guest-check-discount', ['discount_code' => 'FIX50'])
            ->assertStatus(200);
        $this->assertEquals(150, (float) Cart::withoutGlobalScope('cookie_id')
            ->where('cookie_id', $this->guestCookie)->where('status', 0)->first()->discounted_price);

        $response = $this->withHeaders(['guest-id' => $this->guestCookie])
            ->postJson('/api/v1/guest-remove-discount');
        $response->assertStatus(200);
        // json_encode((float) 200) is `200`, so the JSON value is int.
        $response->assertJsonPath('data.new_total', 200);

        $item = Cart::withoutGlobalScope('cookie_id')
            ->where('cookie_id', $this->guestCookie)->where('status', 0)->first();
        $this->assertNull($item->discounted_price);
        $this->assertDatabaseMissing('applied_discount_codes', ['cookie_id' => $this->guestCookie]);

        $this->withHeaders(['guest-id' => $this->guestCookie])->getJson('/api/v1/guest-cart/')
            ->assertStatus(200)
            ->assertJsonPath('data.total_price', 200);
    }

    // ---------- #14: verification SMS is not resent while a code is live ----------

    public function test_unverified_user_with_live_code_gets_no_second_sms(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        User_verfication::create([
            'user_id' => $user->id,
            'code' => '1234',
            'is_reset_password' => false,
            'is_verified' => false,
            'verification_code_expires_at' => now()->addSeconds(60),
        ]);

        // No SMS at all across two requests: both must reuse the
        // live code instead of generating + sending another one.
        $this->mock(moraSms::class, function ($mock) {
            $mock->shouldReceive('sendSms')->never();
        });

        $this->actingAs($user, 'user')->getJson('/api/v1/cart/')
            ->assertStatus(403)
            ->assertJsonPath('verification_required', true);

        $this->actingAs($user, 'user')->getJson('/api/v1/cart/')
            ->assertStatus(403)
            ->assertJsonPath('verification_required', true);
    }

    public function test_unverified_user_with_expired_code_gets_a_fresh_sms(): void
    {
        // The SMS message is built from the settings row's name.
        Setting::create(['website_name' => 'Dabberhaly']);

        $user = User::factory()->create(['password' => 'password']);
        User_verfication::create([
            'user_id' => $user->id,
            'code' => '1234',
            'is_reset_password' => false,
            'is_verified' => false,
            'verification_code_expires_at' => now()->subMinute(),
        ]);

        $this->mock(moraSms::class, function ($mock) {
            $mock->shouldReceive('sendSms')->once()->andReturn(true);
        });

        $this->actingAs($user, 'user')->getJson('/api/v1/cart/')
            ->assertStatus(403)
            ->assertJsonPath('verification_required', true);
    }
}
