<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\User_verfication;
use App\Services\VerificationServices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnverifiedLoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $phone): User
    {
        return User::factory()->create([
            'password' => 'password',
            'phone_number' => $phone,
        ]);
    }

    public function test_login_blocked_while_verification_pending(): void
    {
        $user = $this->makeUser('01000000001');
        app(VerificationServices::class)->setVerificationCode($user->id);

        $this->postJson('/api/v1/login', [
            'phone_number' => '01000000001',
            'password' => 'password',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Account not verified')
            ->assertJsonPath('data.verification_required', true)
            ->assertJsonPath('data.user_id', $user->id);
    }

    public function test_login_allowed_once_verified(): void
    {
        $user = $this->makeUser('01000000002');
        $row = app(VerificationServices::class)->setVerificationCode($user->id);
        User_verfication::where('user_id', $user->id)->update(['is_verified' => true]);

        $this->postJson('/api/v1/login', [
            'phone_number' => '01000000002',
            'password' => 'password',
        ])->assertOk();
    }

    public function test_login_allowed_without_any_verification_row(): void
    {
        $this->makeUser('01000000003');

        $this->postJson('/api/v1/login', [
            'phone_number' => '01000000003',
            'password' => 'password',
        ])->assertOk();
    }
}
