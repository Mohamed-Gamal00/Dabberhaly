<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_guest_is_throttled_to_ten_per_minute(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/create-guest')->assertOk();
        }

        $this->postJson('/api/v1/create-guest')->assertTooManyRequests();
    }
}
