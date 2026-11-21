<?php

namespace Tests\Feature\Api;

use App\Models\Guest;
use App\Models\MainCategory;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestWishlistTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'name' => 'منتج تجريبي',
            'name_en' => 'Test product',
        ], $overrides));
    }

    public function test_guest_can_add_and_remove_a_favourite_with_cookie_id_header(): void
    {
        $guest = Guest::create(['cookie_id' => 'guest-cookie-1']);
        $product = $this->makeProduct();

        // Before the fix this always returned 401 'Guest not found':
        // the controller looked the guest up by numeric id while the
        // header carries the cookie_id.
        $this->withHeaders(['guest-id' => 'guest-cookie-1', 'productId' => (string) $product->id])
            ->postJson('/api/v1/guest-fav-product-add-or-delete')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Product Added To Fav List Successfully');

        $this->assertDatabaseHas('wishlist_products_guest', [
            'guest_id' => $guest->id,
            'product_id' => $product->id,
        ]);

        $this->withHeaders(['guest-id' => 'guest-cookie-1', 'productId' => (string) $product->id])
            ->postJson('/api/v1/guest-fav-product-add-or-delete')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Product Removed From Fav List');

        $this->assertDatabaseMissing('wishlist_products_guest', [
            'guest_id' => $guest->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_guest_add_with_unknown_cookie_id_returns_401(): void
    {
        $product = $this->makeProduct();

        $this->withHeaders(['guest-id' => 'no-such-cookie', 'productId' => (string) $product->id])
            ->postJson('/api/v1/guest-fav-product-add-or-delete')
            ->assertStatus(401);
    }

    public function test_guest_favourite_list_returns_favourited_products(): void
    {
        $guest = Guest::create(['cookie_id' => 'guest-cookie-1']);
        $product = $this->makeProduct();
        $other = $this->makeProduct();
        $guest->wishlistProducts()->attach($product->id);

        // Before the fix this list was always empty: it filtered the
        // wishlist table by the cookie_id string on the numeric FK.
        $response = $this->withHeaders(['guest-id' => 'guest-cookie-1'])
            ->getJson('/api/v1/all-guest-fav-products');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Products Retrieved Successfully');
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($product->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_guest_favourite_list_is_empty_for_unknown_guest(): void
    {
        $this->withHeaders(['guest-id' => 'no-such-cookie'])
            ->getJson('/api/v1/all-guest-fav-products')
            ->assertStatus(200)
            ->assertJsonPath('message', 'No Favourite Products To Retrieved');
    }

    public function test_guest_is_favourite_is_true_in_product_lists(): void
    {
        $guest = Guest::create(['cookie_id' => 'guest-cookie-1']);
        $category = MainCategory::create([
            'name' => 'أدوات منزلية',
            'slug' => 'home-tools',
            'description' => 'كل ما يحتاجه منزلك.',
        ]);
        $product = $this->makeProduct(['category_id' => $category->id, 'discount_price' => 80]);
        $other = $this->makeProduct(['category_id' => $category->id, 'discount_price' => 70]);
        $guest->wishlistProducts()->attach($product->id);

        // Before the fix is_favourite was always false for guests:
        // Helper resolved the guest with Guest::find(cookie_id).
        $response = $this->withHeaders(['guest-id' => 'guest-cookie-1'])
            ->getJson('/api/v1/all-discounts');

        $response->assertStatus(200);
        $byId = collect($response->json('data'))->keyBy('id');
        $this->assertTrue($byId[$product->id]['is_favourite']);
        $this->assertFalse($byId[$other->id]['is_favourite']);
    }
}
