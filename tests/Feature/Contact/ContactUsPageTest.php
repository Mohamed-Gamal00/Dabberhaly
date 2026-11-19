<?php

namespace Tests\Feature\Contact;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContactUsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_receives_contact_details_from_settings(): void
    {
        Setting::create([
            'website_name' => 'Test Store',
            'phone_number' => '+201000000000',
            'email' => 'hello@teststore.com',
            'address' => 'القاهرة، مصر',
        ]);

        $response = $this->get('/contact-us');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ContactUs/Index')
            ->where('contact.phone', '+201000000000')
            ->where('contact.email', 'hello@teststore.com')
            ->where('contact.address', 'القاهرة، مصر')
        );
    }

    public function test_page_renders_with_null_contact_details_when_no_settings_exist(): void
    {
        $response = $this->get('/contact-us');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ContactUs/Index')
            ->where('contact.phone', null)
            ->where('contact.email', null)
            ->where('contact.address', null)
        );
    }

    public function test_submitting_the_form_saves_the_message(): void
    {
        $response = $this->post('/contact-us', [
            'full_name' => 'Mohamed Test',
            'contact_email' => 'customer@example.com',
            'phone_number' => '01000000000',
            'text' => 'Hello, I have a question about my order.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('contact_us', [
            'name' => 'Mohamed Test',
            'email' => 'customer@example.com',
            'message' => 'Hello, I have a question about my order.',
        ]);
    }
}
