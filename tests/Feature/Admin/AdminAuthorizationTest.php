<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Rule;
use App\Models\RuleAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithAbilities(array $abilities): Admin
    {
        $admin = Admin::create([
            'name' => 'Limited Admin',
            'email' => 'limited@example.com',
            'password' => 'password',
            'super_admin' => false,
        ]);

        $rule = Rule::create(['name' => 'Test group']);
        foreach ($abilities as $ability) {
            RuleAbility::create([
                'rule_id' => $rule->id,
                'ability' => $ability,
                'type' => 'allow',
            ]);
        }
        $admin->rules()->attach($rule->id);

        return $admin;
    }
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
        ]);
        // super_admin is not mass-assignable; set it directly.
        $admin->super_admin = true;
        $admin->save();

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

    public function test_non_super_admin_with_payment_ability_can_view_payments(): void
    {
        $admin = $this->adminWithAbilities(['payment.view']);

        $this->actingAs($admin, 'admin')
            ->get('/admin/payments')
            ->assertOk();
    }

    public function test_non_super_admin_without_payment_ability_cannot_view_payments(): void
    {
        $admin = $this->adminWithAbilities(['order.view']);

        $this->actingAs($admin, 'admin')
            ->get('/admin/payments')
            ->assertForbidden();
    }

    public function test_super_admin_can_view_payments(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'password' => 'password',
        ]);
        // super_admin is not mass-assignable; set it directly.
        $admin->super_admin = true;
        $admin->save();

        $this->actingAs($admin, 'admin')
            ->get('/admin/payments')
            ->assertOk();
    }

    public function test_non_super_admin_with_dashboard_ability_can_view_dashboard(): void
    {
        $admin = $this->adminWithAbilities(['dashboard.view']);

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertOk();
    }

    public function test_non_super_admin_without_dashboard_ability_cannot_view_dashboard(): void
    {
        $admin = $this->adminWithAbilities(['order.view']);

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_gates_exist_for_grouped_abilities_only(): void
    {
        // New abilities live only inside the grouped sections of
        // rules.php; AuthServiceProvider flattens them into gates.
        $this->assertTrue(Gate::has('payment.view'));
        $this->assertTrue(Gate::has('dashboard.view'));
        $this->assertTrue(Gate::has('product.trash.view'));

        // Group names are not abilities, and removed abilities
        // (filters, order statuses) have no gate.
        $this->assertFalse(Gate::has('products'));
        $this->assertFalse(Gate::has('filter.view'));
        $this->assertFalse(Gate::has('order_status.view'));
    }

    public function test_rules_create_page_renders_all_sections(): void
    {
        // The groups form renders one section per rules.php group;
        // a section whose group is missing would render empty.
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'password' => 'password',
        ]);
        // super_admin is not mass-assignable; set it directly.
        $admin->super_admin = true;
        $admin->save();

        $this->actingAs($admin, 'admin')
            ->get(route('rules.create'))
            ->assertOk()
            ->assertSee('المدفوعات')
            ->assertSee('طلبات الجملة')
            ->assertSee('لوحة التحكم')
            ->assertDontSee('التصنيفات')
            ->assertDontSee('حالات الطلب');
    }
}
