<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\User_verfication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetPasswordValidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeResetSession(User $user, string $code = '1234'): static
    {
        User_verfication::create([
            'user_id' => $user->id,
            'code' => $code,
            'is_reset_password' => true,
            'is_verified' => false,
            'verification_code_expires_at' => now()->addMinutes(10),
        ]);

        return $this->withSession(['user_id' => $user->id]);
    }

    public function test_reset_password_rejects_weak_password(): void
    {
        // min:8 alone used to accept "aaaaaaaa"; registration requires
        // mixed case + numbers, so reset must too.
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->makeResetSession($user)->post('/update-password', [
            'code' => '1234',
            'password' => 'aaaaaaaa',
            'password_confirmation' => 'aaaaaaaa',
        ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_password_rejects_mismatched_confirmation(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->makeResetSession($user)->post('/update-password', [
            'code' => '1234',
            'password' => 'NewPass123',
            'password_confirmation' => 'OtherPass123',
        ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_password_rejects_short_code(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->makeResetSession($user)->post('/update-password', [
            'code' => '12',
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ]);

        $response->assertSessionHasErrors(['code']);
    }

    public function test_reset_password_accepts_strong_password(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->makeResetSession($user)->post('/update-password', [
            'code' => '1234',
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NewPass123', $user->fresh()->password));
    }

    public function test_forgot_password_rejects_invalid_email_format(): void
    {
        $response = $this->post('/forgot-password', ['email' => 'not-an-email']);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_forgot_password_rejects_unknown_email(): void
    {
        $response = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_forgot_password_accepts_known_email(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');
    }

    public function test_login_rejects_invalid_email_format(): void
    {
        $response = $this->post('/login', [
            'email' => 'not-an-email',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_login_rejects_missing_password(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->post('/login', ['email' => $user->email]);

        $response->assertSessionHasErrors(['password']);
    }

    public function test_verify_code_rejects_non_numeric_code(): void
    {
        $response = $this->post('/verify', ['code' => 'abcd']);

        $response->assertSessionHasErrors(['code']);
    }

    public function test_verify_code_rejects_short_code(): void
    {
        $response = $this->post('/verify', ['code' => '12']);

        $response->assertSessionHasErrors(['code']);
    }
}
