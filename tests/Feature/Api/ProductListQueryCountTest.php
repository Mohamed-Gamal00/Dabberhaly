<?php

namespace Tests\Feature\Api;

use App\Models\Guest;
use App\Models\MainCategory;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductListQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'name' => 'منتج تجريبي',
            'name_en' => 'Test product',
        ], $overrides));
    }

    /** Run a request and return how many queries it executed. */
    private function queryCountFor(callable $request): int
    {
        DB::enableQueryLog();
        $request();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        return count($queries);
    }

    public function test_all_discounts_list_with_ten_products_runs_a_bounded_number_of_queries(): void
    {
        $category = MainCategory::create([
            'name' => 'أدوات منزلية',
            'slug' => 'home-tools',
            'description' => 'كل ما يحتاجه منزلك.',
        ]);
        Product::factory()->count(10)->create([
            'category_id' => $category->id,
            'discount_price' => 80,
        ]);

        $count = $this->queryCountFor(fn () => $this->getJson('/api/v1/all-discounts'));

        // 10 products, one page: products + count + parent +
        // availability + currency rows (cached per request) + one
        // wishlist load. Before the fix the resource lazy-loaded
        // parent and availability per row (~1 + ~20 queries).
        $this->assertLessThan(10, $count, 'Queries run: ' . $count);
        $response = $this->getJson('/api/v1/all-discounts');
        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
    }

    public function test_filter_list_with_ten_products_runs_a_bounded_number_of_queries(): void
    {
        $category = MainCategory::create([
            'name' => 'أدوات منزلية',
            'slug' => 'home-tools',
            'description' => 'كل ما يحتاجه منزلك.',
        ]);
        Product::factory()->count(10)->create(['category_id' => $category->id]);

        $count = $this->queryCountFor(fn () => $this->getJson('/api/v1/filter'));

        $this->assertLessThan(10, $count, 'Queries run: ' . $count);
        $response = $this->getJson('/api/v1/filter');
        $response->assertStatus(200);
        // 10 products on one page of 10: no pagination wrapper.
        $this->assertCount(10, $response->json('data'));
    }

    public function test_filter_resource_returns_currency_and_is_favourite_like_view_resource(): void
    {
        $guest = Guest::create(['cookie_id' => 'guest-cookie-1']);
        $product = $this->makeProduct(['discount_price' => 80]);
        $guest->wishlistProducts()->attach($product->id);

        $response = $this->withHeaders(['guest-id' => 'guest-cookie-1'])
            ->getJson('/api/v1/filter');

        $response->assertStatus(200);
        // One product on one page of 10: no pagination wrapper.
        $record = $response->json('data.0');
        $this->assertArrayHasKey('currency', $record);
        $this->assertArrayHasKey('is_favourite', $record);
        $this->assertTrue($record['is_favourite']);
    }

    public function test_guest_favourite_list_with_ten_products_runs_a_bounded_number_of_queries(): void
    {
        $guest = Guest::create(['cookie_id' => 'guest-cookie-1']);
        $products = Product::factory()->count(10)->create();
        $guest->wishlistProducts()->attach($products->pluck('id')->all());

        $count = $this->queryCountFor(fn () => $this->withHeaders(['guest-id' => 'guest-cookie-1'])
            ->getJson('/api/v1/all-guest-fav-products'));

        $this->assertLessThan(10, $count, 'Queries run: ' . $count);
        $response = $this->withHeaders(['guest-id' => 'guest-cookie-1'])
            ->getJson('/api/v1/all-guest-fav-products');
        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
    }
}
