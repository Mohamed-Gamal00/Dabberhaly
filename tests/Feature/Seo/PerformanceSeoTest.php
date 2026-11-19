<?php

namespace Tests\Feature\Seo;

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\Comment;
use App\Models\MainCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Cart\CartModelRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PerformanceSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cart::resetCookieCache();
    }

    private function makeCategory(string $slug = 'home-tools'): MainCategory
    {
        return MainCategory::create([
            'name' => 'أدوات منزلية',
            'slug' => $slug,
            'description' => 'كل ما يحتاجه منزلك.',
        ]);
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'name' => 'منتج تجريبي',
            'name_en' => 'Test product',
            'description' => 'وصف عربي.',
            'description_en' => 'English description.',
        ], $overrides));
    }

    public function test_api_product_list_with_ten_products_runs_a_bounded_number_of_queries(): void
    {
        $category = $this->makeCategory();
        $this->makeProduct(['category_id' => $category->id]);
        Product::factory()->count(9)->create(['category_id' => $category->id]);

        DB::enableQueryLog();
        $response = $this->getJson('/api/v1/products/' . $category->id);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);
        // 10 products, one page: products + parent + availability +
        // 2 currency lookups (header + default, cached per request).
        // Before the fix this was 60+ currency queries plus one full
        // wishlist load per product.
        $this->assertLessThan(15, count($queries), 'Queries run: ' . json_encode(array_column($queries, 'query')));
        $this->assertCount(10, $response->json('data.data') ?? $response->json('data'));
    }

    public function test_api_product_list_does_not_500_for_a_product_without_a_category(): void
    {
        $product = $this->makeProduct(['category_id' => null]);

        $response = $this->getJson('/api/v1/related-products/' . $product->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.category.id', null);
    }

    public function test_sitemap_returns_200_with_product_and_category_urls(): void
    {
        $category = $this->makeCategory();
        $product = $this->makeProduct(['category_id' => $category->id]);

        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee('/products/' . $product->slug, false);
        $response->assertSee('/categories/' . $category->slug, false);
    }

    public function test_products_page_html_has_no_blade_canonical(): void
    {
        // SeoHead.vue emits the single canonical client-side via
        // @inertiaHead; the blade template must not emit a second one
        // (and must not force the settings canonical_url onto the page).
        $response = $this->get('/products');

        $response->assertStatus(200);
        $this->assertSame(0, substr_count($response->getContent(), 'rel="canonical"'));
    }

    public function test_category_page_paginates_products_at_twelve_per_page(): void
    {
        $category = $this->makeCategory();
        Product::factory()->count(13)->create(['category_id' => $category->id]);

        $response = $this->get('/categories/' . $category->slug);

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 12)
            ->where('products.total', 13)
            ->where('products.last_page', 2));
    }

    public function test_reviews_are_paginated(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $product = $this->makeProduct();
        for ($i = 0; $i < 12; $i++) {
            Comment::create([
                'product_id' => $product->id,
                'user_id' => $user->id,
                'rate' => 5,
                'comment' => 'تعليق تجريبي رقم ' . $i,
            ]);
        }

        $response = $this->getJson('/reviews/' . $product->id);

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('reviews'));
        $response->assertJsonPath('count', 12);
        $response->assertJsonPath('last_page', 2);
    }

    public function test_profile_orders_are_paginated(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        for ($i = 0; $i < 12; $i++) {
            Order::create([
                'user_id' => $user->id,
                'status' => OrderStatus::Pending,
                'return_order' => false,
            ]);
        }

        $response = $this->actingAs($user, 'web')->get('/user-profile');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 10)
            ->where('orders.total', 12)
            ->where('ordersCount', 12));
    }

    public function test_api_search_is_paginated(): void
    {
        Product::factory()->count(12)->create([
            'name' => 'بحث منتج',
            'name_en' => 'Searchable product',
            'category_id' => $this->makeCategory()->id,
        ]);

        $response = $this->getJson('/api/v1/search?product_name=' . urlencode('بحث'));

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data.records'));
        $response->assertJsonPath('data.pagination links.total', 12);
    }

    public function test_cart_total_does_not_break_when_a_product_is_soft_deleted(): void
    {
        $product = $this->makeProduct(['price' => 100, 'discount_price' => 80]);
        $cookieId = (string) Str::ulid();
        Cart::setCookieId($cookieId);
        DB::table('carts')->insert([
            'id' => (string) Str::ulid(),
            'cookie_id' => $cookieId,
            'product_id' => $product->id,
            'status' => 0,
            'quantity' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product->delete();

        $repo = new CartModelRepository();
        $this->assertSame(0.0, $repo->total());
        $this->assertSame(0.0, $repo->totalBeforeDiscount());
    }
}
