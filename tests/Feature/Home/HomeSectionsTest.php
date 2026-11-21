<?php

namespace Tests\Feature\Home;

use App\Models\MainCategory;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Home page sections: they used to all render the same latest-10 list.
 * Now each section has its own list — latest products, discounted
 * offers, the gifts category, and the household-essentials category.
 */
class HomeSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_sections_each_show_their_own_products(): void
    {
        $gifts = MainCategory::create(['name' => 'هدايا', 'name_en' => 'Gifts', 'slug' => 'gifts']);
        $household = MainCategory::create(['name' => 'مستلزمات منزلية', 'name_en' => 'Household Essentials', 'slug' => 'household-essentials']);
        $accessories = MainCategory::create(['name' => 'اكسسوارات', 'name_en' => 'Accessories', 'slug' => 'accessories']);

        // Newest first (explicit created_at so latest() order is deterministic).
        $giftOffer = $this->makeProduct($gifts, ['price' => 200, 'discount_price' => 150, 'created_at' => now()]);
        $householdPlain = $this->makeProduct($household, ['price' => 300, 'discount_price' => null, 'created_at' => now()->subDay()]);
        $householdOffer = $this->makeProduct($household, ['price' => 400, 'discount_price' => 300, 'created_at' => now()->subDays(2)]);
        $giftPlain = $this->makeProduct($gifts, ['price' => 100, 'discount_price' => null, 'created_at' => now()->subDays(3)]);
        $accessoryPlain = $this->makeProduct($accessories, ['price' => 50, 'discount_price' => null, 'created_at' => now()->subDays(4)]);
        // discount_price == price is not a discount, so this is not an offer.
        $noRealDiscount = $this->makeProduct($gifts, ['price' => 120, 'discount_price' => 120, 'created_at' => now()->subDays(5)]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('products', fn ($products) => $this->ids($products) === [
                $giftOffer->id, $householdPlain->id, $householdOffer->id,
                $giftPlain->id, $accessoryPlain->id, $noRealDiscount->id,
            ])
            ->where('offerProducts', fn ($products) => $this->ids($products) === [
                $giftOffer->id, $householdOffer->id,
            ])
            ->where('giftProducts', fn ($products) => $this->ids($products) === [
                $giftOffer->id, $giftPlain->id, $noRealDiscount->id,
            ])
            ->where('householdProducts', fn ($products) => $this->ids($products) === [
                $householdPlain->id, $householdOffer->id,
            ])
        );
    }

    public function test_home_sections_are_empty_without_matching_products(): void
    {
        MainCategory::create(['name' => 'هدايا', 'name_en' => 'Gifts', 'slug' => 'gifts']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('products', [])
            ->where('offerProducts', [])
            ->where('giftProducts', [])
            ->where('householdProducts', [])
        );
    }

    private function makeProduct(MainCategory $category, array $overrides): Product
    {
        return Product::factory()->create(array_merge([
            'category_id' => $category->id,
        ], $overrides));
    }

    /** Ids of a mapped prop list, in order. */
    private function ids($products): array
    {
        return collect($products)->pluck('id')->all();
    }
}
