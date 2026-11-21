<?php

namespace Tests\Feature\Advertisement;

use App\Models\Admin;
use App\Models\Advertisement;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdvertisementMarqueeLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The contact page reads its details from settings.
        Setting::create([
            'website_name' => 'Test Store',
            'phone_number' => '+201000000000',
            'email' => 'hello@teststore.com',
            'address' => 'القاهرة، مصر',
        ]);
    }

    public function test_marquee_switches_language_without_clearing_cache(): void
    {
        Advertisement::create([
            'title' => 'شحن مجاني للطلبات فوق 200 ريال',
            'title_en' => 'Free shipping on orders over 200 SAR',
            'is_active' => true,
        ]);

        // Arabic first — this fills the cache.
        $this->withSession(['locale' => 'ar'])->get('/contact-us')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('advertisements.0.title', 'شحن مجاني للطلبات فوق 200 ريال'));

        // Switch to English without touching the cache: the marquee
        // must follow the site language. Before the fix the cache key
        // was locale-blind, so this request returned the Arabic title.
        $this->withSession(['locale' => 'en'])->get('/contact-us')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('advertisements.0.title', 'Free shipping on orders over 200 SAR'));
    }

    public function test_updating_an_ad_clears_both_locale_caches(): void
    {
        $ad = Advertisement::create([
            'title' => 'عنوان قديم',
            'title_en' => 'Old title',
            'is_active' => true,
        ]);

        // Warm both language caches.
        $this->withSession(['locale' => 'ar'])->get('/contact-us')->assertOk();
        $this->withSession(['locale' => 'en'])->get('/contact-us')->assertOk();

        $this->actingAs($this->makeSuperAdmin(), 'admin')->put(
            route('advertisements.update', $ad),
            ['title' => 'عنوان جديد', 'title_en' => 'New title', 'is_active' => 'on']
        )->assertRedirect();

        $this->withSession(['locale' => 'ar'])->get('/contact-us')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('advertisements.0.title', 'عنوان جديد'));

        $this->withSession(['locale' => 'en'])->get('/contact-us')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('advertisements.0.title', 'New title'));
    }

    private function makeSuperAdmin(): Admin
    {
        return Admin::forceCreate([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'super_admin' => true,
        ]);
    }
}
