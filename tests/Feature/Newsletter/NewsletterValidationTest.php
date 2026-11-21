<?php

namespace Tests\Feature\Newsletter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Newsletter signup (footer): the server rule was already
 * `required|email|max:255` — these tests lock that in, plus the
 * duplicate-subscription 409. The Vue fix covered here: an empty
 * submit used to return silently with no message; it now shows
 * the "email required" message.
 */
class NewsletterValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscribe_rejects_invalid_email(): void
    {
        $response = $this->postJson('/subscribe', ['email' => 'not-an-email']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('send_news_to_users', 0);
    }

    public function test_subscribe_rejects_missing_email(): void
    {
        $response = $this->postJson('/subscribe', ['email' => '']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_subscribe_rejects_overlong_email(): void
    {
        $response = $this->postJson('/subscribe', [
            'email' => str_repeat('a', 250) . '@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_subscribe_accepts_valid_email(): void
    {
        $response = $this->postJson('/subscribe', ['email' => 'customer@example.com']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('send_news_to_users', [
            'subscription_email' => 'customer@example.com',
        ]);
    }

    public function test_subscribe_rejects_duplicate_email_with_409(): void
    {
        $this->postJson('/subscribe', ['email' => 'customer@example.com'])
            ->assertStatus(200);

        $response = $this->postJson('/subscribe', ['email' => 'customer@example.com']);

        $response->assertStatus(409);
        $this->assertDatabaseCount('send_news_to_users', 1);
    }
}
