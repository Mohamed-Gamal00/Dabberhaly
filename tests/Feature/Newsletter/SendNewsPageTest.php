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
        $response->assertViewHas('search', '');
    }

    public function test_search_filters_subscribers_by_email(): void
    {
        SendNewsToUser::forceCreate(['subscription_email' => 'ali@example.com']);
        SendNewsToUser::forceCreate(['subscription_email' => 'mona@example.com']);
        SendNewsToUser::forceCreate(['subscription_email' => 'ali.ahmed@example.com']);

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('user_news.create', ['search' => 'ali']));

        $response->assertOk();
        $response->assertViewHas('search', 'ali');
        // Total stays the overall count even while searching.
        $response->assertViewHas('totalSubscribers', 3);
        $users = $response->viewData('users');
        $this->assertSame(2, $users->total());
        $response->assertSee('ali@example.com');
        $response->assertSee('ali.ahmed@example.com');
        $response->assertDontSee('mona@example.com');
    }

    public function test_subscribers_list_is_paginated(): void
    {
        for ($i = 1; $i <= 55; $i++) {
            SendNewsToUser::forceCreate(['subscription_email' => "user{$i}@example.com"]);
        }

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin, 'admin')->get(route('user_news.create'));

        $response->assertOk();
        $response->assertViewHas('totalSubscribers', 55);
        $users = $response->viewData('users');
        $this->assertSame(50, $users->count());
        $this->assertSame(55, $users->total());

        $page2 = $this->actingAs($admin, 'admin')
            ->get(route('user_news.create', ['page' => 2]));

        $page2->assertOk();
        $this->assertSame(5, $page2->viewData('users')->count());
        $page2->assertSee('user55@example.com');
    }

    private function makeAdmin(): Admin
    {
        return Admin::forceCreate([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'super_admin' => true,
        ]);
    }
}
