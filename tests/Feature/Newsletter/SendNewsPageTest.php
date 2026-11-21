<?php

namespace Tests\Feature\Newsletter;

use App\Models\Admin;
use App\Models\SendNewsToUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin send-news page (GET /admin/send_news): the create() action
 * must pass both the subscribers collection and the total count —
 * the stats card renders $totalSubscribers, so a missing variable
 * is a 500 on the page.
 */
class SendNewsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_news_page_passes_total_subscribers(): void
    {
        SendNewsToUser::forceCreate(['subscription_email' => 'a@example.com']);
        SendNewsToUser::forceCreate(['subscription_email' => 'b@example.com']);

        $admin = Admin::forceCreate([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'super_admin' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('user_news.create'));

        $response->assertOk();
        $response->assertViewHas('users');
        $response->assertViewHas('totalSubscribers', 2);
    }
}
