<?php

namespace Tests\Feature\Profile;

use App\Models\City;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangePersonalInfoApiTest extends TestCase
{
    use RefreshDatabase;

    private function addressPayload(array $overrides = []): array
    {
        $country = Country::create(['name_ar' => 'مصر', 'name_en' => 'Egypt']);
        $city = City::create(['name_ar' => 'القاهرة', 'name_en' => 'Cairo', 'country_id' => $country->id]);

        return array_merge([
            'address_title' => 'Home',
            'first_name' => 'Mohamed',
            'address' => '12 Tahrir St, Dokki, Giza',
            'phone_number' => '01000000000',
            'city_id' => $city->id,
        ], $overrides);
    }

    public function test_api_personal_info_rejects_non_numeric_phone_number(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user, 'user')->postJson('/api/v1/change-personal-info', [
            'first_name' => 'Mohamed',
            'phone_number' => 'abcdef',
        ]);

        $response->assertStatus(422);
        $this->assertNotSame('abcdef', $user->fresh()->phone_number);
    }

    public function test_api_personal_info_rejects_phone_used_by_another_user(): void
    {
        User::factory()->create(['phone_number' => '01111111111', 'password' => 'password']);
        $user = User::factory()->create(['phone_number' => '01000000000', 'password' => 'password']);

        $response = $this->actingAs($user, 'user')->postJson('/api/v1/change-personal-info', [
            'first_name' => 'Mohamed',
            'phone_number' => '01111111111',
        ]);

        $response->assertStatus(422);
        $this->assertSame('01000000000', $user->fresh()->phone_number);
    }

    public function test_api_personal_info_accepts_null_first_name(): void
    {
        // Partial-update endpoint: the controller falls back to the
        // stored value with ??, so an explicit null must not 422.
        $user = User::factory()->create([
            'first_name' => 'Mohamed',
            'phone_number' => '01000000000',
            'password' => 'password',
        ]);

        $response = $this->actingAs($user, 'user')->postJson('/api/v1/change-personal-info', [
            'first_name' => null,
            'phone_number' => '01000000000',
        ]);

        $response->assertStatus(200);
        $this->assertSame('Mohamed', $user->fresh()->first_name);
    }

    public function test_api_personal_info_accepts_valid_update(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user, 'user')->postJson('/api/v1/change-personal-info', [
            'first_name' => 'Omar',
            'phone_number' => '+20 100 000 0000',
        ]);

        $response->assertStatus(200);
        $this->assertSame('Omar', $user->fresh()->first_name);
        $this->assertSame('+20 100 000 0000', $user->fresh()->phone_number);
    }

    public function test_api_create_address_rejects_non_numeric_phone_number(): void
    {
        // The old rule was between:6,16 (length only), so "abcdef" passed.
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user, 'user')->postJson(
            '/api/v1/create-address',
            $this->addressPayload(['phone_number' => 'abcdef'])
        );

        $response->assertStatus(422);
        $this->assertDatabaseCount('user_addresses', 0);
    }

    public function test_api_create_address_accepts_valid_phone_number(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->actingAs($user, 'user')->postJson(
            '/api/v1/create-address',
            $this->addressPayload()
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'phone_number' => '01000000000',
        ]);
    }
}
