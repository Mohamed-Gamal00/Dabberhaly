<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDetailsNullSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_details_works_when_product_has_no_category(): void
    {
        $product = Product::factory()->create([
            'name' => 'منتج تجريبي',
            'description' => 'وصف عربي للمنتج.',
            'description_en' => 'English product description.',
            'category_id' => null,
        ]);

        $response = $this->getJson('/api/v1/product-details/' . $product->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.category.id', null);
        $response->assertJsonPath('data.category.name', null);
    }

    public function test_product_details_works_when_no_currency_rows_exist(): void
    {
        $product = Product::factory()->create([
            'name' => 'منتج تجريبي',
            'description' => 'وصف عربي للمنتج.',
            'description_en' => 'English product description.',
            'category_id' => null,
            'price' => 100,
            'discount_price' => 80,
        ]);

        $this->assertDatabaseCount('currencies', 0);

        $response = $this->getJson('/api/v1/product-details/' . $product->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.price_before_discount', '100');
        $response->assertJsonPath('data.price_after_discount', '80');
        $response->assertJsonPath('data.currency', '');
    }

    public function test_arabic_description_is_returned_without_a_translation_call(): void
    {
        // With an Arabic locale and no English description, the
        // resource used to machine-translate the Arabic text via a
        // live Google request (which also failed with 429s in tests).
        // Translating Arabic to Arabic is a no-op, so the original
        // text must come back with no network call.
        $product = Product::factory()->create([
            'name' => 'منتج تجريبي',
            'description' => 'وصف عربي للمنتج.',
            'description_en' => null,
            'category_id' => null,
        ]);

        $response = $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson('/api/v1/product-details/' . $product->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.description', 'وصف عربي للمنتج.');
    }
}
