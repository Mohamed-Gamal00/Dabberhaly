<?php

namespace Tests\Feature\Footer;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FooterContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_props_carry_contact_details_from_settings(): void
    {
        Setting::create([
            'website_name' => 'Test Store',
            'phone_number' => '+201000000000',
            'email' => 'hello@teststore.com',
            'address' => 'القاهرة، مصر',
            'facebook' => 'https://facebook.com/teststore',
            'instagram' => 'https://instagram.com/teststore',
            'tiktok' => 'https://tiktok.com/@teststore',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('siteContact.phone', '+201000000000')
            ->where('siteContact.email', 'hello@teststore.com')
            ->where('siteContact.address', 'القاهرة، مصر')
            ->where('siteContact.social.facebook', 'https://facebook.com/teststore')
            ->where('siteContact.social.twitter', null)
            ->where('siteContact.social.instagram', 'https://instagram.com/teststore')
            ->where('siteContact.social.snapchat', null)
            ->where('siteContact.social.tiktok', 'https://tiktok.com/@teststore')
        );
    }

    public function test_phone_falls_back_to_phone_field_when_phone_number_is_empty(): void
    {
        Setting::create([
            'website_name' => 'Test Store',
            'phone' => '+966500000000',
            'email' => 'hello@teststore.com',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('siteContact.phone', '+966500000000')
            ->where('siteContact.email', 'hello@teststore.com')
        );
    }

    public function test_shared_contact_props_are_null_when_no_settings_exist(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('siteContact.phone', null)
            ->where('siteContact.email', null)
            ->where('siteContact.address', null)
            ->where('siteContact.social.facebook', null)
            ->where('siteContact.social.twitter', null)
            ->where('siteContact.social.instagram', null)
            ->where('siteContact.social.snapchat', null)
            ->where('siteContact.social.tiktok', null)
        );
    }
}
