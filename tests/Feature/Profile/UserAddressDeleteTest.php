<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAddressDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function makeAddress(User $user, bool $main = false): UserAddress
    {
        return UserAddress::create([
            'user_id' => $user->id,
            'address_title' => $main ? 'Home' : 'Work',
            'first_name' => 'Mohamed',
            'family_name' => 'Gamal',
            'phone_number' => '01000000000',
            'address' => '12 Tahrir St, Dokki, Giza',
            'main_address' => $main,
        ]);
    }

    public function test_user_can_delete_a_non_main_address(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $address = $this->makeAddress($user);

        $response = $this->actingAs($user, 'web')
            ->delete(route('user.addresses.destroy', $address->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('user_addresses', ['id' => $address->id]);
    }

    public function test_user_cannot_delete_the_main_address(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $main = $this->makeAddress($user, main: true);
        $this->makeAddress($user);

        $response = $this->actingAs($user, 'web')
            ->delete(route('user.addresses.destroy', $main->id));

        $response->assertRedirect();
        // The app's default locale is Arabic.
        $response->assertSessionHas('danger', 'لا يمكن حذف عنوان رئيسي.');
        $this->assertDatabaseHas('user_addresses', ['id' => $main->id]);
    }
}
