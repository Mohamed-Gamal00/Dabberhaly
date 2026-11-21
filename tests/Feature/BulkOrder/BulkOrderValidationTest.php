<?php

namespace Tests\Feature\BulkOrder;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validation gap fixed in the web bulk-order form:
 * - phone had no format rule at all — now the shared
 *   Egyptian-mobile rule (App\Rules\EgyptianMobileNumber).
 * Errors are shown next to each field via form.errors (Inertia
 * useForm) — a failed submit keeps the form open with the errors.
 */
class BulkOrderValidationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Mohamed Test',
            'phone' => '01000000000',
            'company_name' => 'Test Co',
            'description' => 'I need 500 units of household supplies.',
        ], $overrides);
    }

    public function test_bulk_order_rejects_non_numeric_phone(): void
    {
        $response = $this->post('/bulk-order', $this->validPayload([
            'phone' => 'not-a-phone',
        ]));

        $response->assertSessionHasErrors('phone');
        $this->assertDatabaseCount('bulk_orders', 0);
    }

    public function test_bulk_order_accepts_phone_with_country_code_plus(): void
    {
        $response = $this->post('/bulk-order', $this->validPayload([
            'phone' => '+201000000000',
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('bulk_orders', ['phone' => '+201000000000']);
    }

    public function test_bulk_order_rejects_too_short_phone(): void
    {
        $response = $this->post('/bulk-order', $this->validPayload([
            'phone' => '12345',
        ]));

        $response->assertSessionHasErrors('phone');
        $this->assertDatabaseCount('bulk_orders', 0);
    }

    // API bulk-order endpoint (POST /api/v1/store-bulk-order): its
    // FormRequest class was missing entirely, so the endpoint 500'd
    // on every call. It now validates with the same Egyptian-mobile
    // rule as the web form.
    public function test_api_bulk_order_accepts_valid_egyptian_phone(): void
    {
        $response = $this->postJson('/api/v1/store-bulk-order', $this->validPayload([
            'phone' => '01054125230',
        ]));

        $response->assertOk();
        $this->assertDatabaseHas('bulk_orders', ['phone' => '01054125230']);
    }

    public function test_api_bulk_order_rejects_wrong_prefix_phone(): void
    {
        $response = $this->postJson('/api/v1/store-bulk-order', $this->validPayload([
            'phone' => '01312345678',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('phone');
        $this->assertDatabaseCount('bulk_orders', 0);
    }
}
