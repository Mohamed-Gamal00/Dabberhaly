<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_super_admin_without_order_ability_cannot_view_orders(): void
    {
        $admin = Admin::create([
            'name' => 'Limited Admin',
            'email' => 'limited@example.com',
            'password' => 'password',
            'super_admin' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/orders')
            ->assertForbidden();
    }

    public function test_super_admin_can_view_orders(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'password' => 'password',
            'super_admin' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/orders')
            ->assertOk();
    }

    public function test_non_super_admin_without_return_ability_cannot_view_returns(): void
    {
        $admin = Admin::create([
            'name' => 'Limited Admin',
            'email' => 'limited@example.com',
            'password' => 'password',
            'super_admin' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/return_orders')
            ->assertForbidden();
    }
}
