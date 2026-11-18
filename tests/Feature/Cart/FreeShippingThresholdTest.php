<?php

namespace Tests\Feature\Cart;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreeShippingThresholdTest extends TestCase
{
    use RefreshDatabase;

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

        $admin = Admin::create([
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
}
