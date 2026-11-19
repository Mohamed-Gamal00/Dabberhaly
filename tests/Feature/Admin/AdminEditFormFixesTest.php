<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\MainCategory;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEditFormFixesTest extends TestCase
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

    public function test_sub_category_edit_form_repopulates_english_name(): void
    {
        $category = MainCategory::create([
            'name' => 'قسم فرعي',
            'name_en' => 'Sub English',
            'slug' => 'sub-category',
        ]);

        $response = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('main_categories.edit', $category));

        $response->assertStatus(200);
        // The English name must appear in BOTH the main tab and the sub
        // tab inputs (the sub tab used old('name', ...) by mistake, so
        // it rendered empty after a validation error).
        $this->assertSame(
            2,
            substr_count($response->getContent(), 'value="Sub English"')
        );
    }

    public function test_pages_edit_form_shows_arabic_title_in_a_visible_input(): void
    {
        $page = Page::create([
            'title' => 'الشروط والأحكام',
            'content' => 'محتوى تجريبي.',
        ]);

        $response = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('pages.edit', $page));

        $response->assertStatus(200);
        // The Arabic title used to be a hidden input, so only the
        // English title was visible on the form.
        $response->assertSee('type="text" name="title"', false);
        $response->assertDontSee('type="hidden" name="title"', false);
        $response->assertSee('value="الشروط والأحكام"', false);
    }
}
