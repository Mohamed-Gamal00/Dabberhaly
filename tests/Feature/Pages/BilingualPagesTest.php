<?php

namespace Tests\Feature\Pages;

use App\Models\Admin;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BilingualPagesTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        return Admin::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'super_admin' => true,
        ]);
    }

    private function makePage(array $overrides = []): Page
    {
        // forceCreate: 'id' is not fillable, and the controller looks
        // pages up by their fixed ids (1=terms, 2=shipping, 3=privacy).
        return Page::forceCreate(array_merge([
            'id' => 1,
            'title' => 'الشروط والأحكام',
            'content' => '<p>المحتوى العربي</p>',
            'title_en' => 'Terms and Conditions',
            'content_en' => '<p>English content</p>',
        ], $overrides));
    }

    public function test_admin_can_save_english_title_and_content_via_pages_update(): void
    {
        $admin = $this->makeAdmin();
        $page = $this->makePage(['title_en' => null, 'content_en' => null]);

        $this->actingAs($admin, 'admin')
            ->put(route('pages.update', $page->id), [
                'title' => 'الشروط والأحكام',
                'content' => '<p>المحتوى العربي</p>',
                'title_en' => 'Terms and Conditions',
                'content_en' => '<p>English content</p>',
            ])
            ->assertRedirect(route('pages.index'));

        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'title_en' => 'Terms and Conditions',
            'content_en' => '<p>English content</p>',
        ]);
    }

    public function test_admin_can_update_without_english_fields(): void
    {
        $admin = $this->makeAdmin();
        $page = $this->makePage();

        $this->actingAs($admin, 'admin')
            ->put(route('pages.update', $page->id), [
                'title' => 'الشروط والأحكام',
                'content' => '<p>محتوى محدث</p>',
            ])
            ->assertRedirect(route('pages.index'));

        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'content' => '<p>محتوى محدث</p>',
            'title_en' => 'Terms and Conditions',
            'content_en' => '<p>English content</p>',
        ]);
    }

    public function test_english_content_is_purified_on_save(): void
    {
        $admin = $this->makeAdmin();
        $page = $this->makePage(['title_en' => null, 'content_en' => null]);

        $this->actingAs($admin, 'admin')
            ->put(route('pages.update', $page->id), [
                'title' => 'الشروط والأحكام',
                'content' => '<p>المحتوى العربي</p>',
                'title_en' => 'Terms and Conditions',
                'content_en' => '<p>English</p><script>alert("xss")</script>',
            ])
            ->assertRedirect(route('pages.index'));

        $this->assertStringNotContainsString('<script>', $page->fresh()->content_en);
        $this->assertStringContainsString('<p>English</p>', $page->fresh()->content_en);
    }

    public function test_web_page_returns_english_when_locale_is_en(): void
    {
        $this->makePage();

        $response = $this->withSession(['locale' => 'en'])->get('/terms-conditions');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('StaticPages/Page')
            ->where('page.title', 'Terms and Conditions')
            ->where('page.content', '<p>English content</p>')
        );
    }

    public function test_web_page_falls_back_to_arabic_when_english_content_is_empty(): void
    {
        $this->makePage(['title_en' => null, 'content_en' => null]);

        $response = $this->withSession(['locale' => 'en'])->get('/terms-conditions');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('StaticPages/Page')
            ->where('page.title', 'الشروط والأحكام')
            ->where('page.content', '<p>المحتوى العربي</p>')
        );
    }

    public function test_web_page_returns_arabic_when_locale_is_ar(): void
    {
        $this->makePage();

        $response = $this->get('/terms-conditions');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('StaticPages/Page')
            ->where('page.title', 'الشروط والأحكام')
            ->where('page.content', '<p>المحتوى العربي</p>')
        );
    }

    public function test_api_returns_english_when_accept_language_is_en(): void
    {
        // The endpoint returns [privacy (id 3), shipping (id 2), terms (id 1)]
        // and answers "data not found" when the privacy page is missing.
        $this->makePage();
        $this->makePage(['id' => 2, 'title' => 'سياسة الشحن', 'content' => '<p>شحن عربي</p>', 'title_en' => 'Shipping Policy', 'content_en' => '<p>English shipping</p>']);
        $this->makePage(['id' => 3, 'title' => 'سياسة الخصوصية', 'content' => '<p>خصوصية عربي</p>', 'title_en' => 'Privacy Policy', 'content_en' => '<p>English privacy</p>']);

        $response = $this->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/static-pages');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.title', 'Privacy Policy');
        $response->assertJsonPath('data.0.content', '<p>English privacy</p>');
        $response->assertJsonPath('data.1.title', 'Shipping Policy');
        $response->assertJsonPath('data.1.content', '<p>English shipping</p>');
        $response->assertJsonPath('data.2.title', 'Terms and Conditions');
        $response->assertJsonPath('data.2.content', '<p>English content</p>');
    }
}
