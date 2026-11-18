<?php

namespace Tests\Feature\Order;

use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnedOrderStatusRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_returned_order_page_has_no_status_update_form(): void
    {
        $admin = Admin::create([
            'name' => 'Admin', 'email' => 'admin@example.com',
            'password' => 'password', 'super_admin' => true,
        ]);
        $user = User::factory()->create(['password' => 'password']);
        $order = Order::create([
            'user_id' => $user->id,
            'return_order' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('return_orders.show', $order->id));

        $response->assertOk();
        $response->assertDontSee('تحديث حالة طلب الإرجاع');
        $response->assertDontSee('return_orders.update', false);
    }

    public function test_returned_order_status_update_route_is_gone(): void
    {
        $admin = Admin::create([
            'name' => 'Admin', 'email' => 'admin@example.com',
            'password' => 'password', 'super_admin' => true,
        ]);
        $user = User::factory()->create(['password' => 'password']);
        $order = Order::create([
            'user_id' => $user->id,
            'return_order' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->put("/admin/return_orders/{$order->id}/update", ['status' => 'delivered'])
            ->assertNotFound();
    }
}
