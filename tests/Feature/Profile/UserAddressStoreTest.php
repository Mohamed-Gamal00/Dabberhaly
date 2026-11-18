<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAddressStoreTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'password' => 'password',
            'first_name' => 'Mohamed',
            'family_name' => 'Gamal',
            'phone_number' => '01000000000',
        ], $overrides));
    }

    public function test_adding_an_address_needs_only_title_and_address(): void
    {
        $user = $this->makeUser();

        // Same payload the address dialog sends: no user_id, city or country.
        $response = $this->actingAs($user, 'web')->post(route('user.addresses.store'), [
            'address_title' => 'Home',
            'address' => '12 Tahrir St, Dokki, Giza',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'address_title' => 'Home',
            'address' => '12 Tahrir St, Dokki, Giza',
            // Contact data comes from the account, not the request.
            'first_name' => 'Mohamed',
            'family_name' => 'Gamal',
            'phone_number' => '01000000000',
        ]);
    }

    public function test_a_spoofed_user_id_is_ignored(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser(['email' => 'other@example.com', 'phone_number' => '01111111111']);

        $this->actingAs($user, 'web')->post(route('user.addresses.store'), [
            'address_title' => 'Home',
            'address' => '12 Tahrir St, Dokki, Giza',
            'user_id' => $other->id,
            'first_name' => 'Someone Else',
            'phone_number' => '09999999999',
        ]);

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'first_name' => 'Mohamed',
            'phone_number' => '01000000000',
        ]);
        $this->assertDatabaseMissing('user_addresses', ['user_id' => $other->id]);
    }

    public function test_adding_an_address_requires_title_and_address(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user, 'web')->post(route('user.addresses.store'), [
            'address_title' => '',
            'address' => '',
        ]);

        $response->assertSessionHasErrors(['address_title', 'address']);
        $this->assertDatabaseCount('user_addresses', 0);
    }

    public function test_updating_an_address_keeps_the_accounts_name_and_phone(): void
    {
        $user = $this->makeUser();
        $address = UserAddress::create([
            'user_id' => $user->id,
            'address_title' => 'Home',
            'first_name' => 'Mohamed',
            'family_name' => 'Gamal',
            'phone_number' => '01000000000',
            'address' => 'Old address',
        ]);

        $this->actingAs($user, 'web')->put(route('user.addresses.update', $address->id), [
            'address_title' => 'Work',
            'address' => 'New address',
            'first_name' => 'Someone Else',
            'phone_number' => '09999999999',
        ]);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $address->id,
            'address_title' => 'Work',
            'address' => 'New address',
            'first_name' => 'Mohamed',
            'phone_number' => '01000000000',
        ]);
    }

    public function test_user_cannot_update_or_delete_another_users_address(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser(['email' => 'other@example.com', 'phone_number' => '01111111111']);
        $address = UserAddress::create([
            'user_id' => $other->id,
            'address_title' => 'Home',
            'first_name' => 'Other',
            'family_name' => 'User',
            'phone_number' => '01111111111',
            'address' => 'Somewhere else',
        ]);

        $this->actingAs($user, 'web')
            ->put(route('user.addresses.update', $address->id), [
                'address_title' => 'Hijacked',
                'address' => 'Hijacked',
            ])
            ->assertNotFound();

        $this->actingAs($user, 'web')
            ->delete(route('user.addresses.destroy', $address->id))
            ->assertNotFound();

        $this->assertDatabaseHas('user_addresses', ['id' => $address->id, 'address_title' => 'Home']);
    }
}
