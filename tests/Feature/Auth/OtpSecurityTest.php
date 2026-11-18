<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\User_verfication;
use App\Services\VerificationServices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OtpSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_otp_is_random_not_hardcoded(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $code = app(VerificationServices::class)->setVerificationCode($user->id);

        $this->assertNotEquals(1111, (int) $code->code);
        $this->assertGreaterThanOrEqual(1000, (int) $code->code);
        $this->assertLessThanOrEqual(9999, (int) $code->code);
    }

    public function test_verify_code_with_wrong_code_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        app(VerificationServices::class)->setVerificationCode($user->id);

        $this->postJson('/api/v1/verify-code', [
            'user_id' => $user->id,
            'code' => '0000',
        ])->assertStatus(403);
    }

    public function test_reset_password_with_unknown_user_is_rejected(): void
    {
        $this->postJson('/api/v1/reset-password', [
            'user_id' => 999999,
            'code_verify' => '1234',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])->assertStatus(422);
    }

    public function test_reset_password_with_wrong_code_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        app(VerificationServices::class)->setVerificationCode($user->id, true);

        $this->postJson('/api/v1/reset-password', [
            'user_id' => $user->id,
            'code_verify' => '0000',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])->assertStatus(403);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_password_with_correct_reset_code_succeeds(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $verification = app(VerificationServices::class)->setVerificationCode($user->id, true);

        $this->postJson('/api/v1/reset-password', [
            'user_id' => $user->id,
            'code_verify' => (string) $verification->code,
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])->assertStatus(200);

        $this->assertTrue(Hash::check('newpassword', $user->fresh()->password));
    }

    public function test_registration_otp_cannot_reset_password(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        // Registration code (is_reset_password = false).
        $verification = app(VerificationServices::class)->setVerificationCode($user->id, false);

        $this->postJson('/api/v1/reset-password', [
            'user_id' => $user->id,
            'code_verify' => (string) $verification->code,
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])->assertStatus(403);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
