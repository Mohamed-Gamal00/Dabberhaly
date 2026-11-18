<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsSecretsTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_secret_fields_keep_the_stored_values(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'password' => 'password',
            'super_admin' => true,
        ]);

        $setting = Setting::create([
            'website_name' => 'Dabberhaly',
            'secret_key' => 'sk_old_value',
            'sms_api_key' => 'sms_old_value',
        ]);

        $this->actingAs($admin, 'admin')
            ->put("/admin/front_settings/{$setting->id}/update", [
                'website_name' => 'Dabberhaly',
                'secret_key' => '',
                'sms_api_key' => '',
            ])
            ->assertRedirect();

        $this->assertSame('sk_old_value', $setting->fresh()->secret_key);
        $this->assertSame('sms_old_value', $setting->fresh()->sms_api_key);
    }

    public function test_provided_secret_fields_are_saved(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'password' => 'password',
            'super_admin' => true,
        ]);

        $setting = Setting::create([
            'website_name' => 'Dabberhaly',
            'secret_key' => 'sk_old_value',
            'sms_api_key' => 'sms_old_value',
        ]);

        $this->actingAs($admin, 'admin')
            ->put("/admin/front_settings/{$setting->id}/update", [
                'website_name' => 'Dabberhaly',
                'secret_key' => 'sk_new_value',
                'sms_api_key' => 'sms_new_value',
            ])
            ->assertRedirect();

        $this->assertSame('sk_new_value', $setting->fresh()->secret_key);
        $this->assertSame('sms_new_value', $setting->fresh()->sms_api_key);
    }
}
