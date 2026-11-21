<?php

namespace Tests\Feature\RepresentativeOrder;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validation gap fixed in the web representative-order form:
 * - phone had no format rule at all — now the shared
 *   Egyptian-mobile rule (App\Rules\EgyptianMobileNumber).
 * Errors are shown next to each field via form.errors (Inertia
 * useForm) — a failed submit keeps the form open with the errors.
 */
class RepresentativeOrderValidationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Mohamed Test',
            'phone' => '01000000000',
            'description' => 'I want to become a sales representative in Cairo.',
        ], $overrides);
    }

    public function test_representative_order_rejects_non_numeric_phone(): void
    {
        $response = $this->post('/representative-order', $this->validPayload([
            'phone' => 'not-a-phone',
        ]));

        $response->assertSessionHasErrors('phone');
        $this->assertDatabaseCount('representatives_orders', 0);
    }

    public function test_representative_order_accepts_phone_with_country_code_plus(): void
    {
        $response = $this->post('/representative-order', $this->validPayload([
            'phone' => '+201000000000',
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('representatives_orders', ['phone' => '+201000000000']);
    }

    public function test_representative_order_rejects_too_short_phone(): void
    {
        $response = $this->post('/representative-order', $this->validPayload([
            'phone' => '12345',
        ]));

        $response->assertSessionHasErrors('phone');
        $this->assertDatabaseCount('representatives_orders', 0);
    }
}
