<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Seed the settings table with initial application configuration.
     *
     * This seeder pulls payment gateway and SMS credentials from .env file
     * to populate the settings table. Make sure these are set in your .env:
     * - MOYASAR_PUBLISHABLE_KEY
     * - MOYASAR_SECRET_KEY
     * - SMS_API_KEY
     * - SMS_USER_NAME
     * - SMS_SENDER
     */
    public function run(): void
    {
        Setting::updateOrCreate(
            ['id' => 1],
            [
                // Neutral placeholders: the store name is the real brand,
                // contact fields stay empty until the owner fills them in
                // /admin/front_settings — fake contact data must never
                // render in the footer or on the contact page.
                'website_name'    => 'دبّرهالي',
                'website_name_en' => 'Dabberhaly',
                'email'           => null,
                'phone'           => null,
                'phone_number'    => null,
                'address'         => null,
                'description'     => 'متجر إلكتروني للمستلزمات المنزلية',
                'value_added_tax' => 0,
                'free_shipping_threshold' => 500,
                // Moyasar keys come from the environment only — no
                // hardcoded fallback keys (a missing key must fail
                // loudly instead of silently using shared test keys).
                'publishable_key' => env('MOYASAR_PUBLISHABLE_KEY'),
                'secret_key'      => env('MOYASAR_SECRET_KEY'),
                'sms_api_key'     => env('SMS_API_KEY'),
                'sms_user_name'   => env('SMS_USER_NAME'),
                'sms_sender'      => env('SMS_SENDER', 'Dabberhaly'),
            ]
        );
    }
}
