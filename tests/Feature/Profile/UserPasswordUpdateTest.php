<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserPasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_password_change_rejects_weak_password(): void
    {
        // min:6 used to accept "aaaaaaaa"; registration requires mixed
        // case + numbers, so the profile change must too.
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user, 'web')->put(route('update_password'), [
            'new_password' => 'aaaaaaaa',
            'new_password_confirmation' => 'aaaaaaaa',
        ]);

        $response->assertSessionHasErrors(['new_password']);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_profile_password_change_rejects_short_password(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user, 'web')->put(route('update_password'), [
            'new_password' => 'Ab1',
            'new_password_confirmation' => 'Ab1',
        ]);

        $response->assertSessionHasErrors(['new_password']);
    }

    public function test_profile_password_change_rejects_mismatched_confirmation(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user, 'web')->put(route('update_password'), [
            'new_password' => 'NewPass123',
            'new_password_confirmation' => 'OtherPass123',
        ]);

        $response->assertSessionHasErrors(['new_password']);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_profile_password_change_accepts_strong_password(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user, 'web')->put(route('update_password'), [
            'new_password' => 'NewPass123',
            'new_password_confirmation' => 'NewPass123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NewPass123', $user->fresh()->password));
    }
}
