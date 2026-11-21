<?php

namespace Tests\Feature\Contact;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validation gaps fixed in the web contact form:
 * - phone_number used `numeric`, rejecting valid phones starting
 *   with `+` — now the shared Egyptian-mobile rule
 *   (App\Rules\EgyptianMobileNumber).
 * - contact_email had no max (column is varchar(255)).
 * Errors are shown next to each field via form.errors (Inertia
 * useForm) — a failed submit keeps the form open with the errors.
 */
class ContactValidationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Mohamed Test',
            'contact_email' => 'customer@example.com',
            'phone_number' => '01000000000',
            'text' => 'Hello, I have a question about my order.',
        ], $overrides);
    }

    public function test_contact_rejects_non_numeric_phone(): void
    {
        $response = $this->post('/contact-us', $this->validPayload([
            'phone_number' => 'not-a-phone',
        ]));

        $response->assertSessionHasErrors('phone_number');
        $this->assertDatabaseCount('contact_us', 0);
    }

    public function test_contact_accepts_phone_with_country_code_plus(): void
    {
        $response = $this->post('/contact-us', $this->validPayload([
            'phone_number' => '+201000000000',
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('contact_us', [
            'phone_number' => '+201000000000',
        ]);
    }

    public function test_contact_accepts_phone_with_spaces_and_dashes(): void
    {
        $response = $this->post('/contact-us', $this->validPayload([
            'phone_number' => '+20 100-000-0000',
        ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('contact_us', [
            'phone_number' => '+20 100-000-0000',
        ]);
    }

    public function test_contact_rejects_too_short_phone(): void
    {
        $response = $this->post('/contact-us', $this->validPayload([
            'phone_number' => '12345',
        ]));

        $response->assertSessionHasErrors('phone_number');
        $this->assertDatabaseCount('contact_us', 0);
    }

    public function test_contact_rejects_invalid_email(): void
    {
        $response = $this->post('/contact-us', $this->validPayload([
            'contact_email' => 'not-an-email',
        ]));

        $response->assertSessionHasErrors('contact_email');
        $this->assertDatabaseCount('contact_us', 0);
    }

    public function test_contact_rejects_overlong_email(): void
    {
        $response = $this->post('/contact-us', $this->validPayload([
            'contact_email' => str_repeat('a', 250) . '@example.com',
        ]));

        $response->assertSessionHasErrors('contact_email');
        $this->assertDatabaseCount('contact_us', 0);
    }
}
