<?php

namespace Tests\Feature\Security;

use App\Models\Admin;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Rule;
use App\Models\RuleAbility;
use App\Models\SendNewsToUser;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\CheckOut\CheckoutServices;
use App\Services\SMSGateways\moraSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Tests for the SECURITY batch of the second production-readiness
 * review: B1, A1, A2, A4, A5, A6 and the SVG upload holes (old #17).
 */
class SecurityBatch2Test extends TestCase
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
            'country_id' => $this->countryId, 'shipping_price' => 25,
        ]);

        // API registration hardcodes country_id 178 for the new address
        // (UserService::createUser), so it must exist for the FK.
        DB::table('countries')->insert([
            'id' => 178, 'name_ar' => 'السعودية', 'name_en' => 'Saudi Arabia',
            'status' => 'used',
        ]);
    }

    protected function tearDown(): void
    {
        Cart::setCookieId(null);

        parent::tearDown();
    }

    private function staffAdmin(array $abilities): Admin
    {
        $admin = Admin::create([
            'name' => 'Staff Admin',
            'email' => 'staff-' . uniqid() . '@example.com',
            // Admin has no 'hashed' cast, so hash explicitly.
            'password' => Hash::make('password'),
            'super_admin' => false,
        ]);

        $rule = Rule::create(['name' => 'Staff group ' . uniqid()]);
        foreach ($abilities as $ability) {
            RuleAbility::create([
                'rule_id' => $rule->id,
                'ability' => $ability,
                'type' => 'allow',
            ]);
        }
        $admin->rules()->attach($rule->id);

        return $admin;
    }

    private function superAdmin(): Admin
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
        ]);
        $admin->super_admin = true;
        $admin->save();

        return $admin->fresh();
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

    /* ---------- B1: staff admin cannot touch their own record ---------- */

    public function test_staff_admin_cannot_update_their_own_record_with_extra_rules(): void
    {
        $staff = $this->staffAdmin(['admin.edit']);
        $ownRuleId = $staff->rules()->first()->id;
        $otherRule = Rule::create(['name' => 'Power group ' . uniqid()]);
        RuleAbility::create([
            'rule_id' => $otherRule->id, 'ability' => 'settings.edit', 'type' => 'allow',
        ]);

        $this->actingAs($staff, 'admin')
            ->put(route('admins.update', $staff->id), [
                'name' => 'Escalated',
                'email' => $staff->email,
                'rules' => [$otherRule->id],
            ])->assertForbidden();

        $this->assertNotEquals('Escalated', $staff->fresh()->name);
        $this->assertEquals([$ownRuleId], $staff->fresh()->rules()->pluck('rules.id')->all());
    }

    public function test_staff_admin_cannot_delete_themselves_or_change_own_password(): void
    {
        $staff = $this->staffAdmin(['admin.delete', 'admin.change_password']);

        $this->actingAs($staff, 'admin')
            ->delete(route('admins.destroy', $staff->id))
            ->assertForbidden();
        $this->assertNotNull(Admin::find($staff->id));

        $this->actingAs($staff, 'admin')
            ->put(route('admins.update_password', $staff->id), [
                'new_password' => 'hacked123',
                'new_password_confirmation' => 'hacked123',
            ])->assertForbidden();
        $this->assertTrue(Hash::check('password', $staff->fresh()->password));
    }

    public function test_super_admin_can_update_another_admin_and_staff_can_update_other_staff(): void
    {
        $super = $this->superAdmin();
        $other = Admin::create([
            'name' => 'Other', 'email' => 'other-' . uniqid() . '@example.com',
            'password' => 'password', 'super_admin' => false,
        ]);
        $rule = Rule::create(['name' => 'Group ' . uniqid()]);

        $this->actingAs($super, 'admin')
            ->put(route('admins.update', $other->id), [
                'name' => 'Renamed by super',
                'email' => $other->email,
                'rules' => [$rule->id],
            ])->assertRedirect();
        $this->assertEquals('Renamed by super', $other->fresh()->name);

        $staff = $this->staffAdmin(['admin.edit']);
        $this->actingAs($staff, 'admin')
            ->put(route('admins.update', $other->id), [
                'name' => 'Renamed by staff',
                'email' => $other->email,
                'rules' => [$rule->id],
            ])->assertRedirect();
        $this->assertEquals('Renamed by staff', $other->fresh()->name);
    }

    /* ---------- A1: API forget-password / register throttling + uniform response ---------- */

    public function test_api_forget_password_is_throttled_after_five_requests(): void
    {
        Setting::create(['website_name' => 'Dabberhaly']);
        $user = User::factory()->create([
            'password' => 'password', 'phone_number' => '01000000101',
        ]);

        $this->mock(moraSms::class, function ($mock) {
            $mock->shouldReceive('sendSms')->atLeast()->once()->andReturn(true);
        });

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/forget-password', [
                'phone_number' => '01000000101',
            ])->assertOk();
        }

        $this->postJson('/api/v1/forget-password', [
            'phone_number' => '01000000101',
        ])->assertTooManyRequests();
    }

    public function test_api_forget_password_returns_same_response_for_unknown_phone(): void
    {
        Setting::create(['website_name' => 'Dabberhaly']);
        $user = User::factory()->create([
            'password' => 'password', 'phone_number' => '01000000102',
        ]);

        $this->mock(moraSms::class, function ($mock) {
            // Exactly one SMS (for the known number). A second call —
            // e.g. for the unknown number — would fail the mock.
            $mock->shouldReceive('sendSms')->once()->andReturn(true);
        });

        $known = $this->postJson('/api/v1/forget-password', [
            'phone_number' => '01000000102',
        ])->assertOk();

        // Unknown phone: same status and same message, no SMS sent.
        $unknown = $this->postJson('/api/v1/forget-password', [
            'phone_number' => '01000000999',
        ])->assertOk();

        $this->assertSame($known->json('message'), $unknown->json('message'));
        $this->assertStringNotContainsString('not exist', $unknown->getContent());
    }

    public function test_api_register_is_throttled_after_five_requests(): void
    {
        Setting::create(['website_name' => 'Dabberhaly']);

        $this->mock(moraSms::class, function ($mock) {
            $mock->shouldReceive('sendSms')->atLeast()->once()->andReturn(true);
        });

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/register', [
                'first_name' => 'Ahmed',
                'family_name' => 'Ali',
                'email' => "api-reg-{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'phone_number' => '0100000011' . $i,
                'address' => 'شارع التحرير 1',
                'city_id' => $this->cityId,
            ])->assertOk();
        }

        $this->postJson('/api/v1/register', [
            'first_name' => 'Ahmed',
            'family_name' => 'Ali',
            'email' => 'api-reg-5@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone_number' => '01000000115',
            'address' => 'شارع التحرير 1',
            'city_id' => $this->cityId,
        ])->assertTooManyRequests();
    }

    public function test_web_register_is_throttled_after_five_requests(): void
    {
        // The throttle middleware runs before FormRequest validation,
        // so even an invalid payload counts — and this keeps the test
        // independent of the email DNS check in the register rules.
        $payload = [
            'first_name' => 'Mohamed',
            'family_name' => 'Gamal',
            'email' => 'not-an-email',
            'phone_number' => '01000000215',
            'address' => 'شارع التحرير 1',
            'country_id' => $this->countryId,
            'city_id' => $this->cityId,
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', $payload)->assertSessionHasErrors('email');
        }

        $this->post('/register', $payload)->assertTooManyRequests();
    }

    /* ---------- A2: web registration OTP invalidated after 5 wrong attempts ---------- */

    public function test_web_registration_otp_is_invalidated_after_five_wrong_codes(): void
    {
        $this->withSession([
            'pending_registration' => [
                'first_name' => 'Mohamed',
                'family_name' => 'Gamal',
                'phone_number' => '01000000301',
                'email' => 'verify-' . uniqid() . '@example.com',
                'password' => Hash::make('password'),
                'address' => 'شارع التحرير 1',
                'country_id' => $this->countryId,
                'city_id' => $this->cityId,
            ],
            'pending_otp' => [
                'code' => '1234',
                'expires_at' => now()->addMinutes(10),
            ],
        ]);

        for ($i = 0; $i < 4; $i++) {
            $this->post('/verify', ['code' => '0000'])
                ->assertRedirect()
                ->assertSessionHas('error', __('flash.invalid_code'));
        }

        // 5th wrong attempt: the code is invalidated.
        $this->post('/verify', ['code' => '0000'])
            ->assertRedirect()
            ->assertSessionHas('error', __('flash.code_expired'))
            ->assertSessionMissing('pending_otp');

        // No user was created from the guessed codes.
        $this->assertDatabaseMissing('users', ['phone_number' => '01000000301']);
    }

    /* ---------- A4: RulesController::destroy cannot delete own group ---------- */

    public function test_staff_admin_cannot_delete_their_own_group(): void
    {
        $staff = $this->staffAdmin(['admin.group.delete']);
        $ownRule = $staff->rules()->first();
        $otherRule = Rule::create(['name' => 'Other group ' . uniqid()]);

        $this->actingAs($staff, 'admin')
            ->delete(route('rules.destroy', $ownRule->id))
            ->assertForbidden();
        $this->assertNotNull(Rule::find($ownRule->id));

        $this->actingAs($staff, 'admin')
            ->delete(route('rules.destroy', $otherRule->id))
            ->assertRedirect();
        $this->assertNull(Rule::find($otherRule->id));
    }

    /* ---------- A5: checkout pre-check never leaks exception details ---------- */

    private function checkoutSetupWithoutShippingRow(): array
    {
        Setting::create(['value_added_tax' => 0, 'free_shipping_threshold' => null]);
        [$user, $address] = $this->makeUserWithAddress();
        $product = Product::factory()->create([
            'price' => 200, 'discount_price' => null, 'quantity' => 50, 'weight' => 1,
        ]);
        Cart::withoutGlobalScope('cookie_id')->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'status' => 0,
            'cookie_id' => 'test-cookie',
        ]);

        return [$user, $address];
    }

    public function test_api_checkout_precheck_returns_generic_message_not_exception_text(): void
    {
        [$user, $address] = $this->checkoutSetupWithoutShippingRow();

        // An UNEXPECTED exception (e.g. an internal error) must never
        // leak its text: force one by mocking the service.
        $this->mock(CheckoutServices::class, function (MockInterface $mock) {
            $mock->shouldReceive('checkShippingSelectOptions')
                ->andThrow(new \Exception('SQLSTATE[HY000]: General error in /var/www/app/Services/CheckOut/CheckoutServices.php'));
        });

        $response = $this->actingAs($user, 'user')->postJson('/api/v1/checkout', [
            'user_address' => (string) $address->id,
            'payment_method' => 'cash_on_delivery',
            'fixed_shipping' => '1',
        ]);

        $response->assertStatus(400);
        $this->assertSame('Order creation failed', $response->json('message'));
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('/var/www', $response->getContent());
    }

    public function test_web_checkout_precheck_returns_generic_message_not_exception_text(): void
    {
        [$user, $address] = $this->checkoutSetupWithoutShippingRow();
        Cart::setCookieId('test-cookie');

        // An UNEXPECTED exception (e.g. an internal error) must never
        // leak its text: force one by mocking the service.
        $this->mock(CheckoutServices::class, function (MockInterface $mock) {
            $mock->shouldReceive('checkShippingSelectOptions')
                ->andThrow(new \Exception('SQLSTATE[HY000]: General error in /var/www/app/Services/CheckOut/CheckoutServices.php'));
        });

        $response = $this->actingAs($user, 'web')->post(route('checkout.store'), [
            'user_address' => (string) $address->id,
            'payment_method' => 'cash_on_delivery',
            'fixed_shipping' => '1',
        ]);

        $response->assertStatus(400);
        $this->assertSame(__('flash.order_creation_failed'), $response->json('message'));
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('/var/www', $response->getContent());
    }

    public function test_api_checkout_precheck_shows_deliberate_checkout_error_message(): void
    {
        // A deliberate CheckoutException (missing shipping setup) IS
        // user-facing: its message is shown, not hidden behind the
        // generic one.
        [$user, $address] = $this->checkoutSetupWithoutShippingRow();

        $response = $this->actingAs($user, 'user')->postJson('/api/v1/checkout', [
            'user_address' => (string) $address->id,
            'payment_method' => 'cash_on_delivery',
            'fixed_shipping' => '1',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('طريقة الشحن', $response->json('message'));
    }

    public function test_web_checkout_precheck_shows_deliberate_checkout_error_message(): void
    {
        // A deliberate CheckoutException (missing shipping setup) IS
        // user-facing: its message is shown, not hidden behind the
        // generic one.
        [$user, $address] = $this->checkoutSetupWithoutShippingRow();
        Cart::setCookieId('test-cookie');

        $response = $this->actingAs($user, 'web')->post(route('checkout.store'), [
            'user_address' => (string) $address->id,
            'payment_method' => 'cash_on_delivery',
            'fixed_shipping' => '1',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('طريقة الشحن', $response->json('message'));
    }

    /* ---------- A6: sending news needs news.send, not news.view ---------- */

    public function test_news_send_requires_news_send_ability_not_news_view(): void
    {
        $this->assertTrue(Gate::has('news.send'));

        $viewer = $this->staffAdmin(['news.view']);
        $subscriber = SendNewsToUser::create(['subscription_email' => 'sub@example.com']);

        $this->actingAs($viewer, 'admin')
            ->post(route('user_news.send'), [
                'title' => 'Spam',
                'body' => 'Buy now',
                'users' => [$subscriber->id],
            ])->assertForbidden();

        $sender = $this->staffAdmin(['news.send']);

        $this->actingAs($sender, 'admin')
            ->post(route('user_news.send'), [
                'title' => 'Hello',
                'body' => 'Newsletter body',
                'users' => [$subscriber->id],
            ])->assertRedirect()
            ->assertSessionHas('success', 'تم استلام طلب إرسال النشرة وسيتم إرسالها للمشتركين');
    }

    /* ---------- SVG uploads rejected in the three admin places ---------- */

    public function test_admin_profile_image_rejects_svg(): void
    {
        $admin = $this->superAdmin();
        $svg = UploadedFile::fake()->create('avatar.svg', 10, 'image/svg+xml');

        $this->actingAs($admin, 'admin')
            ->put(route('profile.update', $admin->id), [
                'name' => $admin->name,
                'email' => $admin->email,
                'image' => $svg,
            ])->assertSessionHasErrors('image');
    }

    public function test_store_feature_image_rejects_svg(): void
    {
        $staff = $this->staffAdmin(['store_feature.create']);
        $svg = UploadedFile::fake()->create('feature.svg', 10, 'image/svg+xml');

        $this->actingAs($staff, 'admin')
            ->post(route('store_featuers.store'), [
                'title' => 'Feature',
                'image' => $svg,
            ])->assertSessionHasErrors('image');

        $this->assertDatabaseCount('store_featuers', 0);
    }

    public function test_header_banner_image_rejects_svg(): void
    {
        $staff = $this->staffAdmin(['header_banner.edit']);
        $svg = UploadedFile::fake()->create('banner.svg', 10, 'image/svg+xml');

        $this->actingAs($staff, 'admin')
            ->post(route('header_banner.sotreAndUpdate'), [
                'header' => [
                    ['image' => $svg, 'image_link' => 'https://example.com'],
                ],
            ])->assertSessionHasErrors('header.0.image');
    }
}
