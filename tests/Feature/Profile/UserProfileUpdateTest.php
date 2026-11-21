<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile_info(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Ahmed',
            'family_name' => 'Ali',
            'phone_number' => '01000000000',
            // The factory's hardcoded cost-10 hash fails Hash::verifyConfiguration
            // under phpunit's BCRYPT_ROUNDS=4; a plain password is hashed by the cast.
            'password' => 'password',
        ]);

        $response = $this->actingAs($user, 'web')->put(route('update_user_info'), [
            'first_name' => 'Mohamed',
            'family_name' => 'Gamal',
            'phone_number' => '01111111111',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Mohamed',
            'family_name' => 'Gamal',
            'phone_number' => '01111111111',
        ]);
        // Email is readonly in the profile form and must stay untouched.
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_profile_update_validates_required_fields(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user, 'web')->put(route('update_user_info'), [
            'first_name' => '',
            'phone_number' => '123',
        ]);

        $response->assertSessionHasErrors(['first_name', 'phone_number']);
    }

    public function test_profile_update_rejects_phone_number_used_by_another_user(): void
    {
        User::factory()->create(['phone_number' => '01111111111', 'password' => 'password']);
        $user = User::factory()->create(['phone_number' => '01000000000', 'password' => 'password']);

        $response = $this->actingAs($user, 'web')->put(route('update_user_info'), [
            'first_name' => 'Mohamed',
            'family_name' => 'Gamal',
            'phone_number' => '01111111111',
        ]);

        $response->assertSessionHasErrors(['phone_number']);
        $this->assertSame('01000000000', $user->fresh()->phone_number);
    }

    public function test_profile_update_rejects_email_used_by_another_user(): void
    {
        User::factory()->create(['email' => 'taken@example.com', 'password' => 'password']);
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user, 'web')->put(route('update_user_info'), [
            'first_name' => 'Mohamed',
            'family_name' => 'Gamal',
            'phone_number' => '01000000000',
            'email' => 'taken@example.com',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertNotSame('taken@example.com', $user->fresh()->email);
    }

    public function test_profile_update_allows_keeping_own_phone_and_email(): void
    {
        $user = User::factory()->create([
            'phone_number' => '01000000000',
            'email' => 'mine@example.com',
            'password' => 'password',
        ]);

        $response = $this->actingAs($user, 'web')->put(route('update_user_info'), [
            'first_name' => 'Mohamed',
            'family_name' => 'Gamal',
            'phone_number' => '01000000000',
            'email' => 'mine@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame('01000000000', $user->fresh()->phone_number);
        $this->assertSame('mine@example.com', $user->fresh()->email);
    }

    public function test_profile_update_rejects_non_numeric_phone_number(): void
    {
        // The old rule was between:6,16 (length only), so a 6-letter
        // string like "abcdef" passed validation and was stored.
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user, 'web')->put(route('update_user_info'), [
            'first_name' => 'Mohamed',
            'family_name' => 'Gamal',
            'phone_number' => 'abcdef',
        ]);

        $response->assertSessionHasErrors(['phone_number']);
        $this->assertNotSame('abcdef', $user->fresh()->phone_number);
    }

    public function test_profile_update_rejects_phone_shorter_than_seven_digits(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user, 'web')->put(route('update_user_info'), [
            'first_name' => 'Mohamed',
            'family_name' => 'Gamal',
            'phone_number' => '123456',
        ]);

        $response->assertSessionHasErrors(['phone_number']);
    }

    public function test_profile_update_accepts_phone_with_plus_and_spaces(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user, 'web')->put(route('update_user_info'), [
            'first_name' => 'Mohamed',
            'family_name' => 'Gamal',
            'phone_number' => '+20 100 000 0000',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame('+20 100 000 0000', $user->fresh()->phone_number);
    }

    public function test_profile_update_rejects_family_name_over_255_characters(): void
    {
        // family_name errors used to be swallowed in the UI: the field
        // had no :error-messages binding. The server must still reject it.
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user, 'web')->put(route('update_user_info'), [
            'first_name' => 'Mohamed',
            'family_name' => str_repeat('a', 256),
            'phone_number' => '01000000000',
        ]);

        $response->assertSessionHasErrors(['family_name']);
    }
}
