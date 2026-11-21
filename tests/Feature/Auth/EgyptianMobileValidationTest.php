<?php

namespace Tests\Feature\Auth;

use App\Models\City;
use App\Models\Country;
use App\Models\Setting;
use App\Models\User;
use App\Services\SMSGateways\moraSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature coverage for the shared Egyptian-mobile rule
 * (App\Rules\EgyptianMobileNumber) on the main forms:
 * web + API registration, API login, API forget-password,
 * API contact-us (the old +-rejection bug) and the API
 * representative order.
 */
class EgyptianMobileValidationTest extends TestCase
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
            'country_id' => $this->countryId, 'shipping_price' => 25, 'code' => 1,
        ]);
    }

    // ---------- web registration ----------

    private function webRegisterPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Mohamed',
            'family_name' => 'Gamal',
            'email' => 'new-customer@example.com',
            'phone_number' => '01054125230',
            'address' => '12 Tahrir St, Dokki, Giza',
            'country_id' => $this->countryId,
            'city_id' => $this->cityId,
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ], $overrides);
    }

    public function test_web_register_accepts_each_carrier_prefix(): void
    {
        // The user's examples: 010 (Vodafone), 011 (Etisalat),
        // 012 (Orange), 015 (We — completed to 11 digits).
        // NOTE: the register rules also require `email:rfc,dns`, and
        // this sandbox has no DNS resolution, so every post also
        // fails on email. The assertion is therefore that the ONLY
        // error is email — i.e. the phone passed validation.
        $phones = ['01054125230', '01115433208', '01225874559', '01559555555'];

        foreach ($phones as $i => $phone) {
            $response = $this->post(route('auth.register'), $this->webRegisterPayload([
                'email' => "carrier-{$i}@example.com",
                'phone_number' => $phone,
            ]));

            $messages = session('errors')->getBag('default')->messages();
            $this->assertSame(['email'], array_keys($messages), "phone {$phone} should pass validation");
            $this->assertNull(session('pending_registration'));
        }
    }

    public function test_web_register_rejects_wrong_prefix_and_wrong_length(): void
    {
        $invalid = [
            '01312345678',  // wrong prefix
            '0101234567',   // 10 digits
            '010123456789', // 12 digits
        ];

        foreach ($invalid as $i => $phone) {
            $response = $this->post(route('auth.register'), $this->webRegisterPayload([
                'email' => "bad-phone-a{$i}@example.com",
                'phone_number' => $phone,
            ]));

            $response->assertSessionHasErrors('phone_number');
            $this->assertNull(session('pending_registration'));
        }
    }

    public function test_web_register_rejects_letters_landline_and_short_015(): void
    {
        $invalid = [
            '0101234567a', // letters
            '0223456789',  // landline
            '015595555',   // the 015 example as given: 9 digits, 2 short
        ];

        foreach ($invalid as $i => $phone) {
            $response = $this->post(route('auth.register'), $this->webRegisterPayload([
                'email' => "bad-phone-b{$i}@example.com",
                'phone_number' => $phone,
            ]));

            $response->assertSessionHasErrors('phone_number');
            $this->assertNull(session('pending_registration'));
        }
    }

    // ---------- API registration / login / forget-password ----------

    private function apiRegisterPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Ahmed',
            'family_name' => 'Ali',
            'email' => 'api-reg@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone_number' => '01512345678',
            'address' => 'شارع التحرير 1',
            'city_id' => $this->cityId,
        ], $overrides);
    }

    public function test_api_register_accepts_015_and_rejects_013(): void
    {
        Setting::create(['website_name' => 'Dabberhaly']);

        $this->mock(moraSms::class, function ($mock) {
            $mock->shouldReceive('sendSms')->once()->andReturn(true);
        });

        $this->postJson('/api/v1/register', $this->apiRegisterPayload())
            ->assertOk();
        $this->assertDatabaseHas('users', ['phone_number' => '01512345678']);

        $this->postJson('/api/v1/register', $this->apiRegisterPayload([
            'email' => 'api-reg-013@example.com',
            'phone_number' => '01312345678',
        ]))->assertStatus(422);
        $this->assertDatabaseMissing('users', ['phone_number' => '01312345678']);
    }

    public function test_api_login_rejects_invalid_phone_format(): void
    {
        $this->postJson('/api/v1/login', [
            'phone_number' => '01312345678',
            'password' => 'password',
        ])->assertStatus(422);
    }

    public function test_api_login_accepts_valid_phone_format(): void
    {
        // No verification row: the user is not blocked from logging in.
        User::factory()->create(['password' => 'password', 'phone_number' => '01054125230']);

        $this->postJson('/api/v1/login', [
            'phone_number' => '01054125230',
            'password' => 'password',
        ])->assertOk();
    }

    public function test_api_forget_password_rejects_invalid_phone_format(): void
    {
        Setting::create(['website_name' => 'Dabberhaly']);

        $this->postJson('/api/v1/forget-password', [
            'phone_number' => '01312345678',
        ])->assertStatus(422);

        // A well-formed but unknown number keeps the uniform 200 reply.
        $this->postJson('/api/v1/forget-password', [
            'phone_number' => '01000000999',
        ])->assertOk();
    }

    // ---------- API contact-us (+-rejection bug) / representative order ----------

    public function test_api_contact_accepts_plus20_and_rejects_013(): void
    {
        $this->postJson('/api/v1/contact-us', [
            'full_name' => 'Mohamed Test',
            'email' => 'customer@example.com',
            'phone_number' => '+201012345678',
            'message' => 'Hello, I have a question about my order.',
        ])->assertOk();
        $this->assertDatabaseHas('contact_us', ['phone_number' => '+201012345678']);

        $this->postJson('/api/v1/contact-us', [
            'full_name' => 'Mohamed Test',
            'email' => 'customer@example.com',
            'phone_number' => '01312345678',
            'message' => 'Hello, I have a question about my order.',
        ])->assertStatus(422);
    }

    public function test_api_representative_order_accepts_valid_and_rejects_invalid_phone(): void
    {
        $this->postJson('/api/v1/store-represintative-order', [
            'name' => 'مندوب',
            'phone' => '01225874559',
            'description' => 'طلب',
        ])->assertOk();
        $this->assertDatabaseHas('representatives_orders', ['phone' => '01225874559']);

        $this->postJson('/api/v1/store-represintative-order', [
            'name' => 'مندوب',
            'phone' => '0555123456',
            'description' => 'طلب',
        ])->assertStatus(422);
    }

    // ---------- API personal info / web profile ----------

    public function test_api_personal_info_rejects_wrong_prefix(): void
    {
        $user = User::factory()->create(['password' => 'password', 'phone_number' => '01000000000']);

        $response = $this->actingAs($user, 'user')->postJson('/api/v1/change-personal-info', [
            'first_name' => 'Mohamed',
            'phone_number' => '01312345678',
        ]);

        $response->assertStatus(422);
        $this->assertSame('01000000000', $user->fresh()->phone_number);
    }

    public function test_web_profile_update_rejects_wrong_prefix(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user, 'web')->put(route('update_user_info'), [
            'first_name' => 'Mohamed',
            'family_name' => 'Gamal',
            'phone_number' => '01312345678',
        ]);

        $response->assertSessionHasErrors(['phone_number']);
    }
}
