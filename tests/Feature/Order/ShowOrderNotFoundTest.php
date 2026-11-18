<?php

namespace Tests\Feature\Order;

use App\Models\Guest;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowOrderNotFoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_show_order_returns_404_when_missing(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->actingAs($user, 'user')
            ->getJson('/api/v1/user_show_order/999999')
            ->assertNotFound();
    }

    public function test_guest_show_order_returns_404_when_missing(): void
    {
        $guest = Guest::create(['cookie_id' => 'test-guest-cookie']);

        $this->withHeaders(['guest-id' => (string) $guest->id])
            ->getJson('/api/v1/guest-order/999999')
            ->assertNotFound();
    }

    public function test_guest_fav_with_unknown_guest_returns_401(): void
    {
        $this->withHeaders(['guest-id' => '999999'])
            ->postJson('/api/v1/guest-fav-product-add-or-delete', [], ['productId' => '1'])
            ->assertUnauthorized();
    }
}
