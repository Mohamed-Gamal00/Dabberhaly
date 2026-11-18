<?php

namespace Tests\Feature\Order;

use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnedOrderRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        return Admin::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'super_admin' => true,
        ]);
    }

    public function test_opening_a_returned_order_redirects_to_its_returns_page(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create(['password' => 'password']);
        $order = Order::create([
            'user_id' => $user->id,
            'return_order' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('orders.show', $order->id));

        $response->assertRedirect(route('return_orders.show', $order->id));
    }

    public function test_opening_a_normal_order_still_shows_the_order_page(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create(['password' => 'password']);
        $order = Order::create([
            'user_id' => $user->id,
            'return_order' => false,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('orders.show', $order->id));

        $response->assertOk();
    }

    public function test_opening_a_missing_order_still_404s(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->get(route('orders.show', 999999))
            ->assertNotFound();
    }
}
