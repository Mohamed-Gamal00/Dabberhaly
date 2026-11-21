<?php

namespace Tests\Feature\Api;

use App\Models\MainCategory;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArabicSearchNormalizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $overrides = []): Product
    {
        $category = MainCategory::firstOrCreate(
            ['slug' => 'home-tools'],
            ['name' => 'أدوات منزلية', 'description' => 'كل ما يحتاجه منزلك.'],
        );

        return Product::factory()->create(array_merge([
            'name' => 'منتج تجريبي',
            'name_en' => 'Test product',
            'category_id' => $category->id,
        ], $overrides));
    }

    /** Product names returned by GET /api/v1/search for the given query. */
    private function apiSearchNames(string $query): array
    {
        $response = $this->getJson('/api/v1/search?product_name=' . urlencode($query));

        $response->assertStatus(200);

        return collect($response->json('data'))->pluck('product_name')->all();
    }

    public function test_api_search_matches_alef_variants_in_either_direction(): void
    {
        $this->makeProduct(['name' => 'خلاط أحمد المميز']);

        // Query typed with a bare alef finds the stored alef-with-hamza.
        $this->assertContains('خلاط أحمد المميز', $this->apiSearchNames('احمد'));
        // And the exact stored spelling still works.
        $this->assertContains('خلاط أحمد المميز', $this->apiSearchNames('أحمد'));
        $this->assertContains('خلاط أحمد المميز', $this->apiSearchNames('إحمد'));
    }

    public function test_api_search_matches_teh_marbuta_and_heh_in_either_direction(): void
    {
        $this->makeProduct(['name' => 'قهوة عربية']);
        $this->makeProduct(['name' => 'قهوه تركية']);

        // Query with ه finds the product stored with ة, and vice versa.
        $this->assertContains('قهوة عربية', $this->apiSearchNames('قهوه'));
        $this->assertContains('قهوه تركية', $this->apiSearchNames('قهوة'));
    }

    public function test_api_search_matches_alef_maksura_and_yeh_in_either_direction(): void
    {
        $this->makeProduct(['name' => 'عصير موسى']);
        $this->makeProduct(['name' => 'عصير موسي']);

        $this->assertContains('عصير موسى', $this->apiSearchNames('موسي'));
        $this->assertContains('عصير موسي', $this->apiSearchNames('موسى'));
    }

    public function test_api_search_ignores_diacritics_in_the_query(): void
    {
        $this->makeProduct(['name' => 'خلاط أحمد المميز']);

        $this->assertContains('خلاط أحمد المميز', $this->apiSearchNames('أَحْمَد'));
    }

    public function test_api_search_still_matches_english_and_unrelated_terms_find_nothing(): void
    {
        $this->makeProduct(['name' => 'خلاط أحمد المميز', 'name_en' => 'Blender Pro']);

        $this->assertContains('خلاط أحمد المميز', $this->apiSearchNames('blender'));

        $response = $this->getJson('/api/v1/search?product_name=' . urlencode('zzz-no-such-product'));
        $response->assertStatus(200);
        $response->assertJsonPath('message', 'No Matching Data');
        $this->assertSame([], $response->json('data'));
    }

    public function test_web_products_page_matches_alef_variants(): void
    {
        $this->makeProduct(['name' => 'خلاط أحمد المميز']);

        $this->get('/products?search=' . urlencode('احمد'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.name', 'خلاط أحمد المميز'));
    }

    public function test_web_live_search_matches_alef_variants(): void
    {
        $this->makeProduct(['name' => 'خلاط أحمد المميز']);

        $response = $this->getJson('/search?q=' . urlencode('احمد'));

        $response->assertOk();
        $this->assertContains('خلاط أحمد المميز', collect($response->json())->pluck('name')->all());
    }
}
