<?php

namespace Tests\Feature\Security;

use App\Events\UserForgotPassword;
use App\Models\Admin;
use App\Models\Product;
use App\Models\Rule;
use App\Models\RuleAbility;
use App\Models\SendNewsToUser;
use App\Models\Setting;
use App\Models\User;
use App\Models\User_verfication;
use App\Services\SMSGateways\moraSms;
use App\Services\VerificationServices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class SecurityBlockersTest extends TestCase
{
    use RefreshDatabase;

    private function staffAdmin(array $abilities): Admin
    {
        $admin = Admin::create([
            'name' => 'Staff Admin',
            'email' => 'staff-' . uniqid() . '@example.com',
            'password' => 'password',
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
        // super_admin is deliberately NOT mass-assignable (removed from
        // $fillable), so it must be set directly, never via create().
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
        ]);
        $admin->super_admin = true;
        $admin->save();

        return $admin->fresh();
    }

    private function mockSms(bool $sent = true): void
    {
        $this->mock(moraSms::class, function ($mock) use ($sent) {
            $mock->shouldReceive('sendSms')->atLeast()->once()->andReturn($sent);
        });
    }

    /* ---------- 1. OTP leak: code never in any API response ---------- */

    public function test_forget_password_response_never_contains_the_code_and_sms_is_sent(): void
    {
        Setting::create(['website_name' => 'Dabberhaly']);
        $user = User::factory()->create([
            'password' => 'password',
            'phone_number' => '01000000001',
        ]);

        $this->mock(moraSms::class, function ($mock) {
            $mock->shouldReceive('sendSms')
                ->once()
                ->with('01000000001', Mockery::any())
                ->andReturn(true);
        });

        $response = $this->postJson('/api/v1/forget-password', [
            'phone_number' => '01000000001',
        ]);

        $response->assertOk();

        $code = (string) User_verfication::where('user_id', $user->id)
            ->where('is_reset_password', true)->value('code');

        $this->assertNotEmpty($code);
        $this->assertStringNotContainsString($code, $response->getContent());
    }

    public function test_forget_password_reports_failure_when_sms_fails_and_still_hides_code(): void
    {
        Setting::create(['website_name' => 'Dabberhaly']);
        $user = User::factory()->create([
            'password' => 'password',
            'phone_number' => '01000000002',
        ]);

        $this->mock(moraSms::class, function ($mock) {
            $mock->shouldReceive('sendSms')->once()->andReturn(false);
        });

        $response = $this->postJson('/api/v1/forget-password', [
            'phone_number' => '01000000002',
        ]);

        $response->assertStatus(500);

        $code = (string) User_verfication::where('user_id', $user->id)
            ->where('is_reset_password', true)->value('code');

        $this->assertStringNotContainsString($code, $response->getContent());
    }

    /* ---------- 3. API OTP attempts invalidate the code ---------- */

    public function test_five_wrong_verify_codes_invalidate_the_code(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
            'phone_number' => '01000000003',
        ]);
        $verification = app(VerificationServices::class)->setVerificationCode($user->id);

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/verify-code', [
                'user_id' => $user->id,
                'code' => '0000',
            ])->assertStatus(403);
        }

        // 5th wrong attempt: code is invalidated.
        $this->postJson('/api/v1/verify-code', [
            'user_id' => $user->id,
            'code' => '0000',
        ])->assertStatus(403);

        $this->assertNull(User_verfication::where('user_id', $user->id)
            ->where('is_reset_password', false)->first());

        // Even the correct code no longer works.
        $this->postJson('/api/v1/verify-code', [
            'user_id' => $user->id,
            'code' => (string) $verification->code,
        ])->assertStatus(403);
    }

    public function test_five_wrong_reset_codes_invalidate_the_reset_code(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
            'phone_number' => '01000000004',
        ]);
        $verification = app(VerificationServices::class)->setVerificationCode($user->id, true);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/reset-password', [
                'user_id' => $user->id,
                'code_verify' => '0000',
                'password' => 'newpassword',
                'password_confirmation' => 'newpassword',
            ])->assertStatus(403);
        }

        $this->assertNull(User_verfication::where('user_id', $user->id)
            ->where('is_reset_password', true)->first());

        $this->postJson('/api/v1/reset-password', [
            'user_id' => $user->id,
            'code_verify' => (string) $verification->code,
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])->assertStatus(403);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    /* ---------- 4. Admin privilege escalation ---------- */

    public function test_staff_admin_cannot_change_super_admin_password(): void
    {
        $staff = $this->staffAdmin(['admin.change_password']);
        $super = $this->superAdmin();

        $this->actingAs($staff, 'admin')
            ->put(route('admins.update_password', $super->id), [
                'new_password' => 'hacked123',
                'new_password_confirmation' => 'hacked123',
            ])->assertForbidden();

        $this->assertTrue(Hash::check('password', $super->fresh()->password));
    }

    public function test_staff_admin_cannot_delete_super_admin(): void
    {
        $staff = $this->staffAdmin(['admin.delete']);
        $super = $this->superAdmin();

        $this->actingAs($staff, 'admin')
            ->delete(route('admins.destroy', $super->id))
            ->assertForbidden();

        $this->assertNotNull(Admin::find($super->id));
    }

    public function test_staff_admin_cannot_update_super_admin(): void
    {
        $staff = $this->staffAdmin(['admin.edit']);
        $super = $this->superAdmin();

        $this->actingAs($staff, 'admin')
            ->put(route('admins.update', $super->id), [
                'name' => 'Taken Over',
                'email' => $super->email,
                'rules' => [],
            ])->assertForbidden();

        $this->assertNotEquals('Taken Over', $super->fresh()->name);
    }

    public function test_staff_admin_can_change_password_of_another_staff_admin(): void
    {
        $staff = $this->staffAdmin(['admin.change_password']);
        $other = Admin::create([
            'name' => 'Other Staff',
            'email' => 'other-' . uniqid() . '@example.com',
            'password' => 'password',
            'super_admin' => false,
        ]);

        $this->actingAs($staff, 'admin')
            ->put(route('admins.update_password', $other->id), [
                'new_password' => 'newpass123',
                'new_password_confirmation' => 'newpass123',
            ])->assertRedirect();

        $this->assertTrue(Hash::check('newpass123', $other->fresh()->password));
    }

    public function test_staff_admin_cannot_edit_their_own_group(): void
    {
        $staff = $this->staffAdmin(['admin.group.edit']);
        $ownRule = $staff->rules()->first();

        $otherRule = Rule::create(['name' => 'Other group ' . uniqid()]);

        // Own group: forbidden.
        $this->actingAs($staff, 'admin')
            ->put(route('rules.update', $ownRule->id), [
                'name' => $ownRule->name,
                'abilities' => ['product.view' => 'allow'],
            ])->assertForbidden();

        // A group that is not theirs: allowed.
        $this->actingAs($staff, 'admin')
            ->put(route('rules.update', $otherRule->id), [
                'name' => $otherRule->name,
                'abilities' => ['product.view' => 'allow'],
            ])->assertRedirect();
    }

    public function test_admin_store_rejects_nonexistent_rule_ids(): void
    {
        $staff = $this->staffAdmin(['admin.create']);

        $this->actingAs($staff, 'admin')
            ->post(route('admins.store'), [
                'name' => 'New Admin',
                'email' => 'new-' . uniqid() . '@example.com',
                'new_password' => 'password',
                'new_password_confirmation' => 'password',
                'rules' => [999999],
            ])->assertSessionHasErrors('rules.0');

        $this->assertDatabaseMissing('admins', ['name' => 'New Admin']);
    }

    /* ---------- 5. Missing Gates: product update + newsletter ---------- */

    public function test_product_update_requires_product_edit_ability(): void
    {
        $staff = $this->staffAdmin(['product.view']);
        $product = Product::factory()->create();
        $category = \App\Models\MainCategory::create(['name' => 'Test Category']);

        // Valid payload: the FormRequest validation runs before the
        // controller, so only a valid request reaches the Gate check.
        $this->actingAs($staff, 'admin')
            ->put(route('products.update', $product->id), [
                'name' => 'Hacked price',
                'price' => 1,
                'status' => 'active',
                'parent_id' => $category->id,
            ])->assertForbidden();

        $this->assertNotEquals(1, (float) $product->fresh()->price);
    }

    public function test_product_image_delete_requires_product_edit_ability(): void
    {
        $staff = $this->staffAdmin(['product.view']);

        $this->actingAs($staff, 'admin')
            ->post(route('image.delete'), ['image_id' => 999999])
            ->assertForbidden();
    }

    public function test_newsletter_send_requires_news_ability(): void
    {
        $staff = $this->staffAdmin(['order.view']);
        $subscriber = SendNewsToUser::create(['subscription_email' => 'sub@example.com']);

        $this->actingAs($staff, 'admin')
            ->post(route('user_news.send'), [
                'title' => 'Spam',
                'body' => 'Buy now',
                'users' => [$subscriber->id],
            ])->assertForbidden();
    }

    public function test_newsletter_send_with_ability_uses_honest_flash_message(): void
    {
        $staff = $this->staffAdmin(['news.view']);
        $subscriber = SendNewsToUser::create(['subscription_email' => 'sub@example.com']);

        $this->actingAs($staff, 'admin')
            ->post(route('user_news.send'), [
                'title' => 'Hello',
                'body' => 'Newsletter body',
                'users' => [$subscriber->id],
            ])->assertRedirect()
            ->assertSessionHas('success', 'تم استلام طلب إرسال النشرة وسيتم إرسالها للمشتركين');
    }

    /* ---------- 6/14. Login brute force ---------- */

    public function test_api_login_is_throttled_after_repeated_failures(): void
    {
        $phone = '01000000005';
        User::factory()->create(['password' => 'password', 'phone_number' => $phone]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', [
                'phone_number' => $phone,
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        // 6th attempt: locked out even before checking the password.
        $this->postJson('/api/v1/login', [
            'phone_number' => $phone,
            'password' => 'password',
        ])->assertStatus(429);
    }

    public function test_web_login_is_throttled_after_repeated_failures(): void
    {
        $email = 'web-throttle-' . uniqid() . '@example.com';
        User::factory()->create(['password' => 'password', 'email' => $email]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => $email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        // 6th attempt with the CORRECT password is still locked out.
        $this->post('/login', [
            'email' => $email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_admin_login_is_throttled_after_repeated_failures(): void
    {
        $email = 'admin-throttle-' . uniqid() . '@example.com';
        Admin::create([
            'name' => 'Throttle Admin',
            'email' => $email,
            'password' => Hash::make('password'),
            'super_admin' => false,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', [
                'email' => $email,
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        // 6th attempt with the CORRECT password is still locked out.
        $this->post('/admin/login', [
            'email' => $email,
            'password' => 'password',
        ])->assertRedirect();

        $this->assertGuest('admin');
    }

    /* ---------- 2/12. Web password reset: sync code + attempts ---------- */

    public function test_forgot_password_creates_code_synchronously_without_queue(): void
    {
        // Even with the event faked (listener never runs), the code must
        // already exist — it is created in the request path, not the job.
        Event::fake([UserForgotPassword::class]);

        $user = User::factory()->create([
            'password' => 'password',
            'email' => 'reset-' . uniqid() . '@example.com',
        ]);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect();

        $this->assertNotNull(User_verfication::where('user_id', $user->id)
            ->where('is_reset_password', true)->first());

        Event::assertDispatched(UserForgotPassword::class);
    }

    public function test_web_reset_five_wrong_codes_invalidate_the_code(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
            'email' => 'reset2-' . uniqid() . '@example.com',
        ]);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect();

        $verification = User_verfication::where('user_id', $user->id)
            ->where('is_reset_password', true)->first();
        $this->assertNotNull($verification);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/update-password', [
                'code' => '0000',
                'password' => 'newpassword',
                'password_confirmation' => 'newpassword',
            ]);
        }

        // The code is gone after the 5th wrong attempt.
        $this->assertNull(User_verfication::where('user_id', $user->id)
            ->where('is_reset_password', true)->first());

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    /* ---------- 9. SVG uploads rejected ---------- */

    public function test_customer_profile_image_rejects_svg(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $token = $user->createToken('-AuthToken')->plainTextToken;

        $svg = \Illuminate\Http\UploadedFile::fake()->create('avatar.svg', 10, 'image/svg+xml');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/change-profile-image', ['image' => $svg])
            ->assertStatus(422);
    }

    /* ---------- 10. Secrets are encrypted at rest ---------- */

    public function test_setting_secrets_are_stored_encrypted(): void
    {
        $setting = Setting::create([
            'website_name' => 'Dabberhaly',
            'secret_key' => 'sk_test_secret',
            'sms_api_key' => 'sms_test_key',
        ]);

        $raw = \Illuminate\Support\Facades\DB::table('settings')
            ->where('id', $setting->id)->first();

        $this->assertNotEquals('sk_test_secret', $raw->secret_key);
        $this->assertNotEquals('sms_test_key', $raw->sms_api_key);

        // And they still read back decrypted through the model.
        $this->assertEquals('sk_test_secret', $setting->fresh()->secret_key);
        $this->assertEquals('sms_test_key', $setting->fresh()->sms_api_key);
    }

    /* ---------- 13. Admin model: hidden + super_admin not fillable ---------- */

    public function test_admin_password_is_hidden_and_super_admin_not_mass_assignable(): void
    {
        $admin = Admin::create([
            'name' => 'Hidden Admin',
            'email' => 'hidden-' . uniqid() . '@example.com',
            'password' => 'password',
            'super_admin' => true,
        ]);

        // Mass assignment must NOT set super_admin (DB default is false).
        $this->assertFalse((bool) $admin->fresh()->super_admin);
        $this->assertArrayNotHasKey('password', $admin->toArray());
        $this->assertArrayNotHasKey('remember_token', $admin->toArray());
    }
}
