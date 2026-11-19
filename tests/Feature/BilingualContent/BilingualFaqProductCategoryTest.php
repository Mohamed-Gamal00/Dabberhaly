<?php

namespace Tests\Feature\BilingualContent;

use App\Models\Admin;
use App\Models\CommonQuestion;
use App\Models\MainCategory;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BilingualFaqProductCategoryTest extends TestCase
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

    private function makeQuestion(array $overrides = []): CommonQuestion
    {
        return CommonQuestion::create(array_merge([
            'title' => 'كيف يتم الشحن؟',
            'description' => 'يتم الشحن خلال يومين.',
            'title_en' => 'How is shipping done?',
            'description_en' => 'Shipping takes two days.',
        ], $overrides));
    }

    private function makeCategory(array $overrides = []): MainCategory
    {
        return MainCategory::create(array_merge([
            'name' => 'أدوات منزلية',
            'slug' => 'home-tools',
            'description' => 'كل ما يحتاجه منزلك.',
            'description_en' => 'Everything your home needs.',
        ], $overrides));
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'name' => 'منتج تجريبي',
            'description' => 'وصف عربي للمنتج.',
            'description_en' => 'English product description.',
        ], $overrides));
    }

    // ---------------- FAQ ----------------

    public function test_admin_can_save_english_fields_via_faq_store(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->post(route('common_questions.store'), [
                'title' => 'كيف يتم الشحن؟',
                'description' => 'يتم الشحن خلال يومين.',
                'title_en' => 'How is shipping done?',
                'description_en' => 'Shipping takes two days.',
            ])
            ->assertRedirect(route('common_questions.index'));

        $this->assertDatabaseHas('common_questions', [
            'title' => 'كيف يتم الشحن؟',
            'title_en' => 'How is shipping done?',
            'description_en' => 'Shipping takes two days.',
        ]);
    }

    public function test_admin_can_save_english_fields_via_faq_update(): void
    {
        $admin = $this->makeAdmin();
        $question = $this->makeQuestion(['title_en' => null, 'description_en' => null]);

        $this->actingAs($admin, 'admin')
            ->put(route('common_questions.update', $question->id), [
                'title' => 'كيف يتم الشحن؟',
                'description' => 'يتم الشحن خلال يومين.',
                'title_en' => 'How is shipping done?',
                'description_en' => 'Shipping takes two days.',
            ])
            ->assertRedirect(route('common_questions.index'));

        $this->assertDatabaseHas('common_questions', [
            'id' => $question->id,
            'title_en' => 'How is shipping done?',
            'description_en' => 'Shipping takes two days.',
        ]);
    }

    public function test_web_faq_returns_english_when_locale_is_en(): void
    {
        $this->makeQuestion();

        $response = $this->withSession(['locale' => 'en'])->get('/faq');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('StaticPages/Questions')
            ->where('questions.0.title', 'How is shipping done?')
            ->where('questions.0.description', 'Shipping takes two days.')
            // Arabic originals are kept for the category keyword filter.
            ->where('questions.0.title_ar', 'كيف يتم الشحن؟')
            ->where('questions.0.description_ar', 'يتم الشحن خلال يومين.')
        );
    }

    public function test_web_faq_falls_back_to_arabic_when_english_is_empty(): void
    {
        $this->makeQuestion(['title_en' => null, 'description_en' => null]);

        $response = $this->withSession(['locale' => 'en'])->get('/faq');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('StaticPages/Questions')
            ->where('questions.0.title', 'كيف يتم الشحن؟')
            ->where('questions.0.description', 'يتم الشحن خلال يومين.')
        );
    }

    public function test_api_faq_returns_english_when_accept_language_is_en(): void
    {
        $this->makeQuestion();

        $response = $this->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/v1/common-questions');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.title', 'How is shipping done?');
        $response->assertJsonPath('data.0.description', 'Shipping takes two days.');
    }

    // ---------------- Product description ----------------

    public function test_admin_can_save_english_description_via_product_update(): void
    {
        $admin = $this->makeAdmin();
        $category = $this->makeCategory();
        $product = $this->makeProduct(['description_en' => null]);

        $this->actingAs($admin, 'admin')
            ->put(route('products.update', $product->id), [
                'name' => 'منتج تجريبي',
                'price' => 100,
                'status' => 'active',
                'parent_id' => $category->id,
                'description' => 'وصف عربي للمنتج.',
                'description_en' => 'English product description.',
            ])
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'description_en' => 'English product description.',
        ]);
    }

    public function test_web_product_page_returns_english_description_when_locale_is_en(): void
    {
        $product = $this->makeProduct();

        $response = $this->withSession(['locale' => 'en'])->get('/products/' . $product->slug);

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Products/Show')
            ->where('product.description', 'English product description.')
        );
    }

    public function test_web_product_page_falls_back_to_arabic_when_english_is_empty(): void
    {
        $product = $this->makeProduct(['description_en' => null]);

        $response = $this->withSession(['locale' => 'en'])->get('/products/' . $product->slug);

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Products/Show')
            ->where('product.description', 'وصف عربي للمنتج.')
        );
    }

    public function test_api_product_details_returns_english_description_when_locale_is_en(): void
    {
        $product = $this->makeProduct();

        $response = $this->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/v1/product-details/' . $product->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.description', 'English product description.');
    }

    // ---------------- Category description ----------------

    public function test_admin_can_save_english_description_via_category_store(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->post(route('main_categories.store'), [
                'name' => 'أدوات منزلية',
                'type' => 'main',
                'description' => 'كل ما يحتاجه منزلك.',
                'description_en' => 'Everything your home needs.',
            ])
            ->assertRedirect(route('main_categories.index'));

        $this->assertDatabaseHas('main_categories', [
            'name' => 'أدوات منزلية',
            'description' => 'كل ما يحتاجه منزلك.',
            'description_en' => 'Everything your home needs.',
        ]);
    }

    public function test_admin_can_save_english_description_via_category_update(): void
    {
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(['description_en' => null]);

        $this->actingAs($admin, 'admin')
            ->put(route('main_categories.update', $category->id), [
                'name' => 'أدوات منزلية',
                'type' => 'main',
                'description' => 'كل ما يحتاجه منزلك.',
                'description_en' => 'Everything your home needs.',
            ])
            ->assertRedirect(route('main_categories.index'));

        $this->assertDatabaseHas('main_categories', [
            'id' => $category->id,
            'description_en' => 'Everything your home needs.',
        ]);
    }

    public function test_web_category_page_returns_english_description_when_locale_is_en(): void
    {
        $category = $this->makeCategory();

        $response = $this->withSession(['locale' => 'en'])->get('/categories/' . $category->slug);

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Categories/Show')
            ->where('category.description', 'Everything your home needs.')
        );
    }

    public function test_web_category_page_falls_back_to_arabic_when_english_is_empty(): void
    {
        $category = $this->makeCategory(['description_en' => null]);

        $response = $this->withSession(['locale' => 'en'])->get('/categories/' . $category->slug);

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Categories/Show')
            ->where('category.description', 'كل ما يحتاجه منزلك.')
        );
    }
}
