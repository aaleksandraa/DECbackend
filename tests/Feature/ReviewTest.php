<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Salon;
use App\Models\Review;
use App\Models\User;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    protected Salon $salon;
    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->salon = Salon::factory()->create(['status' => 'approved']);
        $this->client = User::factory()->create(['role' => 'klijent']);
    }

    /**
     * Test creating a review
     */
    public function test_create_review(): void
    {
        $response = $this->actingAs($this->client)->postJson("/api/v1/reviews", [
            'salon_id' => $this->salon->id,
            'rating' => 5,
            'comment' => 'Odličan salon, preporučujem!',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reviews', [
            'salon_id' => $this->salon->id,
            'user_id' => $this->client->id,
            'rating' => 5,
        ]);
    }

    /**
     * Test review validation - missing rating
     */
    public function test_review_validation_missing_rating(): void
    {
        $response = $this->actingAs($this->client)->postJson("/api/v1/reviews", [
            'salon_id' => $this->salon->id,
            'comment' => 'Odličan salon, preporučujem!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('rating');
    }

    /**
     * Test review validation - invalid rating (too low)
     */
    public function test_review_validation_invalid_rating_too_low(): void
    {
        $response = $this->actingAs($this->client)->postJson("/api/v1/reviews", [
            'salon_id' => $this->salon->id,
            'rating' => 0,
            'comment' => 'Loš salon',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('rating');
    }

    /**
     * Test review validation - invalid rating (too high)
     */
    public function test_review_validation_invalid_rating_too_high(): void
    {
        $response = $this->actingAs($this->client)->postJson("/api/v1/reviews", [
            'salon_id' => $this->salon->id,
            'rating' => 6,
            'comment' => 'Odličan salon',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('rating');
    }

    /**
     * Test review validation - comment too short
     */
    public function test_review_validation_comment_too_short(): void
    {
        $response = $this->actingAs($this->client)->postJson("/api/v1/reviews", [
            'salon_id' => $this->salon->id,
            'rating' => 5,
            'comment' => 'OK',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('comment');
    }

    /**
     * Test review validation - comment too long
     */
    public function test_review_validation_comment_too_long(): void
    {
        $longComment = str_repeat('a', 1001);

        $response = $this->actingAs($this->client)->postJson("/api/v1/reviews", [
            'salon_id' => $this->salon->id,
            'rating' => 5,
            'comment' => $longComment,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('comment');
    }

    /**
     * Test user cannot create duplicate review
     */
    public function test_user_cannot_create_duplicate_review(): void
    {
        Review::factory()->create([
            'salon_id' => $this->salon->id,
            'user_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->client)->postJson("/api/v1/reviews", [
            'salon_id' => $this->salon->id,
            'rating' => 5,
            'comment' => 'Another review',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test getting salon reviews
     */
    public function test_get_salon_reviews(): void
    {
        Review::factory()->count(5)->create(['salon_id' => $this->salon->id]);

        $response = $this->getJson("/api/v1/salons/{$this->salon->id}/reviews");

        $response->assertStatus(200);
        $reviews = $response->json('data');
        $this->assertCount(5, $reviews);
    }

    /**
     * Test review pagination
     */
    public function test_review_pagination(): void
    {
        Review::factory()->count(15)->create(['salon_id' => $this->salon->id]);

        $response = $this->getJson("/api/v1/salons/{$this->salon->id}/reviews?per_page=5");

        $response->assertStatus(200);
        $reviews = $response->json('data');
        $this->assertCount(5, $reviews);
    }

    /**
     * Test updating own review
     */
    public function test_update_own_review(): void
    {
        $review = Review::factory()->create([
            'salon_id' => $this->salon->id,
            'user_id' => $this->client->id,
            'rating' => 3,
        ]);

        $response = $this->actingAs($this->client)->putJson("/api/v1/reviews/{$review->id}", [
            'rating' => 5,
            'comment' => 'Updated comment',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 5,
        ]);
    }

    /**
     * Test user cannot update other's review
     */
    public function test_user_cannot_update_others_review(): void
    {
        $otherClient = User::factory()->create(['role' => 'klijent']);
        $review = Review::factory()->create([
            'salon_id' => $this->salon->id,
            'user_id' => $otherClient->id,
        ]);

        $response = $this->actingAs($this->client)->putJson("/api/v1/reviews/{$review->id}", [
            'rating' => 1,
            'comment' => 'Hacked review',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test deleting own review
     */
    public function test_delete_own_review(): void
    {
        $review = Review::factory()->create([
            'salon_id' => $this->salon->id,
            'user_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->client)->deleteJson("/api/v1/reviews/{$review->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    /**
     * Test user cannot delete other's review
     */
    public function test_user_cannot_delete_others_review(): void
    {
        $otherClient = User::factory()->create(['role' => 'klijent']);
        $review = Review::factory()->create([
            'salon_id' => $this->salon->id,
            'user_id' => $otherClient->id,
        ]);

        $response = $this->actingAs($this->client)->deleteJson("/api/v1/reviews/{$review->id}");

        $response->assertStatus(403);
    }

    /**
     * Test salon rating is calculated correctly
     */
    public function test_salon_rating_calculation(): void
    {
        Review::factory()->create(['salon_id' => $this->salon->id, 'rating' => 5]);
        Review::factory()->create(['salon_id' => $this->salon->id, 'rating' => 4]);
        Review::factory()->create(['salon_id' => $this->salon->id, 'rating' => 3]);

        $response = $this->getJson("/api/v1/salons/{$this->salon->id}");

        $response->assertStatus(200);
        $rating = $response->json('data.rating');
        $this->assertEquals(4, $rating); // Average of 5, 4, 3
    }

    /**
     * Test review sorting by rating
     */
    public function test_review_sorting_by_rating(): void
    {
        Review::factory()->create(['salon_id' => $this->salon->id, 'rating' => 3]);
        Review::factory()->create(['salon_id' => $this->salon->id, 'rating' => 5]);
        Review::factory()->create(['salon_id' => $this->salon->id, 'rating' => 4]);

        $response = $this->getJson("/api/v1/salons/{$this->salon->id}/reviews?sort=rating&direction=desc");

        $response->assertStatus(200);
        $reviews = $response->json('data');
        $this->assertEquals(5, $reviews[0]['rating']);
        $this->assertEquals(4, $reviews[1]['rating']);
        $this->assertEquals(3, $reviews[2]['rating']);
    }

    /**
     * Test unauthenticated user cannot create review
     */
    public function test_unauthenticated_user_cannot_create_review(): void
    {
        $response = $this->postJson("/api/v1/reviews", [
            'salon_id' => $this->salon->id,
            'rating' => 5,
            'comment' => 'Great salon!',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test review with all valid ratings
     */
    public function test_review_with_all_valid_ratings(): void
    {
        for ($rating = 1; $rating <= 5; $rating++) {
            $response = $this->actingAs(User::factory()->create(['role' => 'klijent']))->postJson(
                "/api/v1/reviews",
                [
                    'salon_id' => $this->salon->id,
                    'rating' => $rating,
                    'comment' => "Rating {$rating} comment",
                ]
            );

            $response->assertStatus(201);
        }

        $this->assertDatabaseCount('reviews', 5);
    }
}
