<?php

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\MainCategory;
use App\Models\Product;
use App\Models\ProductAvailability;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiCartFreeShippingThresholdTest extends TestCase
{
    use RefreshDatabase;

    /**
     * CartResource serialises products through their parent category
     * and availability, both null without these rows.
     */
    private function makeProduct(): Product
    {
        $category = MainCategory::create(['name' => 'Test Category']);
        $availability = ProductAvailability::create(['name' => 'متاح', 'name_en' => 'In Stock']);

        return Product::factory()->create([
            'price' => 200,
            'quantity' => 10,
            'category_id' => $category->id,
            'product_availability_id' => $availability->id,
        ]);
    }

    public function test_user_api_cart_returns_threshold_from_settings(): void
    {
        Setting::create([
            'website_name' => 'Test Store',
            'free_shipping_threshold' => 300,
        ]);

        $user = User::factory()->create(['password' => 'password']);
        $product = $this->makeProduct();
        Cart::withoutGlobalScope('cookie_id')->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'status' => 0,
            'cookie_id' => 'test-cookie',
        ]);

        $this->actingAs($user, 'user')
            ->getJson('/api/v1/cart/')
            ->assertStatus(200)
            ->assertJsonPath('data.free_shipping_threshold', 300);
    }

    public function test_guest_api_cart_returns_threshold_from_settings(): void
    {
        Setting::create([
            'website_name' => 'Test Store',
            'free_shipping_threshold' => 300,
        ]);

        $product = $this->makeProduct();
        Cart::withoutGlobalScope('cookie_id')->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'status' => 0,
            'cookie_id' => 'guest-cookie-1',
        ]);

        $this->withHeaders(['guest-id' => 'guest-cookie-1'])
            ->getJson('/api/v1/guest-cart/')
            ->assertStatus(200)
            ->assertJsonPath('data.free_shipping_threshold', 300);
    }

    public function test_api_carts_return_default_threshold_when_no_setting_exists(): void
    {
        $this->withHeaders(['guest-id' => 'guest-cookie-1'])
            ->getJson('/api/v1/guest-cart/')
            ->assertStatus(200)
            ->assertJsonPath('data.free_shipping_threshold', 500);
    }
}
