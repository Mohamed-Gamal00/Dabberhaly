<?php

namespace Tests\Feature\Review;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Product review form: the server rules were already
 * `rate: integer 1-5` and `comment: min:5 max:500` — these tests
 * lock them in, including that a failed submit returns the error
 * as JSON (the Vue form shows it under the comment box via
 * reviewError) instead of swallowing it.
 */
class ReviewValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['password' => 'password']);
        $this->product = Product::factory()->create(['quantity' => 10]);
    }

    private function postReview(array $overrides = [])
    {
        return $this->actingAs($this->user, 'web')->postJson('/reviews', array_merge([
            'product_id' => $this->product->id,
            'rate' => 5,
            'comment' => 'Great product, highly recommended.',
        ], $overrides));
    }

    public function test_review_accepts_valid_rating_and_comment(): void
    {
        $response = $this->postReview();

        $response->assertStatus(200);
        $this->assertDatabaseHas('comments', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'rate' => 5,
        ]);
    }

    public function test_review_rejects_rating_above_five(): void
    {
        $response = $this->postReview(['rate' => 6]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('rate');
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_review_rejects_rating_below_one(): void
    {
        $response = $this->postReview(['rate' => 0]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('rate');
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_review_rejects_comment_shorter_than_five_chars(): void
    {
        $response = $this->postReview(['comment' => 'Good']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('comment');
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_review_rejects_comment_longer_than_500_chars(): void
    {
        $response = $this->postReview(['comment' => str_repeat('a', 501)]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('comment');
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_review_rejects_unknown_product(): void
    {
        $response = $this->postReview(['product_id' => 999999]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('product_id');
    }

    public function test_review_rejects_second_review_of_same_product(): void
    {
        $this->postReview()->assertStatus(200);

        $response = $this->postReview(['comment' => 'A second, different review text.']);

        $response->assertStatus(422);
        $this->assertDatabaseCount('comments', 1);
    }
}
