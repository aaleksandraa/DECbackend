<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    protected User $client;
    protected Salon $salon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = User::factory()->create(['role' => 'klijent']);
        $this->salon = Salon::factory()->create(['status' => 'approved']);
    }

    /**
     * Test adding salon to favorites
     */
    public function test_add_salon_to_favorites(): void
    {
        $response = $this->actingAs($this->client)->postJson(
            "/api/v1/favorites/{$this->salon->id}"
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('favorites', [
            'user_id' => $this->client->id,
            'salon_id' => $this->salon->id,
        ]);
    }

    /**
     * Test cannot add same salon twice
     */
    public function test_cannot_add_same_salon_twice(): void
    {
        $this->actingAs($this->client)->postJson("/api/v1/favorites/{$this->salon->id}");

        $response = $this->actingAs($this->client)->postJson(
            "/api/v1/favorites/{$this->salon->id}"
        );

        $response->assertStatus(422);
    }

    /**
     * Test removing salon from favorites
     */
    public function test_remove_salon_from_favorites(): void
    {
        $this->actingAs($this->client)->postJson("/api/v1/favorites/{$this->salon->id}");

        $response = $this->actingAs($this->client)->deleteJson(
            "/api/v1/favorites/{$this->salon->id}"
        );

        $response->assertStatus(200);
        $this->assertDatabaseMissing('favorites', [
            'user_id' => $this->client->id,
            'salon_id' => $this->salon->id,
        ]);
    }

    /**
     * Test getting user favorites
     */
    public function test_get_user_favorites(): void
    {
        $salon1 = Salon::factory()->create(['status' => 'approved']);
        $salon2 = Salon::factory()->create(['status' => 'approved']);
        $salon3 = Salon::factory()->create(['status' => 'approved']);

        $this->actingAs($this->client)->postJson("/api/v1/favorites/{$salon1->id}");
        $this->actingAs($this->client)->postJson("/api/v1/favorites/{$salon2->id}");
        $this->actingAs($this->client)->postJson("/api/v1/favorites/{$salon3->id}");

        $response = $this->actingAs($this->client)->getJson('/api/v1/favorites');

        $response->assertStatus(200);
        $favorites = $response->json('data');
        $this->assertCount(3, $favorites);
    }

    /**
     * Test favorite pagination
     */
    public function test_favorite_pagination(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $salon = Salon::factory()->create(['status' => 'approved']);
            $this->actingAs($this->client)->postJson("/api/v1/favorites/{$salon->id}");
        }

        $response = $this->actingAs($this->client)->getJson('/api/v1/favorites?per_page=5');

        $response->assertStatus(200);
        $favorites = $response->json('data');
        $this->assertCount(5, $favorites);
    }

    /**
     * Test unauthenticated user cannot add favorites
     */
    public function test_unauthenticated_user_cannot_add_favorites(): void
    {
        $response = $this->postJson("/api/v1/favorites/{$this->salon->id}");

        $response->assertStatus(401);
    }

    /**
     * Test adding non-existent salon to favorites
     */
    public function test_adding_non_existent_salon_to_favorites(): void
    {
        $response = $this->actingAs($this->client)->postJson('/api/v1/favorites/99999');

        $response->assertStatus(404);
    }

    /**
     * Test removing non-existent favorite
     */
    public function test_removing_non_existent_favorite(): void
    {
        $response = $this->actingAs($this->client)->deleteJson(
            "/api/v1/favorites/{$this->salon->id}"
        );

        $response->assertStatus(404);
    }

    /**
     * Test different users have different favorites
     */
    public function test_different_users_have_different_favorites(): void
    {
        $client2 = User::factory()->create(['role' => 'klijent']);

        $this->actingAs($this->client)->postJson("/api/v1/favorites/{$this->salon->id}");

        $response1 = $this->actingAs($this->client)->getJson('/api/v1/favorites');
        $response2 = $this->actingAs($client2)->getJson('/api/v1/favorites');

        $this->assertCount(1, $response1->json('data'));
        $this->assertCount(0, $response2->json('data'));
    }

    /**
     * Test favorite count in salon details
     */
    public function test_favorite_count_in_salon_details(): void
    {
        $client1 = User::factory()->create(['role' => 'klijent']);
        $client2 = User::factory()->create(['role' => 'klijent']);

        $this->actingAs($client1)->postJson("/api/v1/favorites/{$this->salon->id}");
        $this->actingAs($client2)->postJson("/api/v1/favorites/{$this->salon->id}");

        $response = $this->getJson("/api/v1/salons/{$this->salon->id}");

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('data.favorites_count'));
    }

    /**
     * Test salon owner cannot add own salon to favorites
     */
    public function test_salon_owner_cannot_add_own_salon_to_favorites(): void
    {
        $owner = User::factory()->create(['role' => 'salon']);
        $salon = Salon::factory()->create(['owner_id' => $owner->id, 'status' => 'approved']);

        $response = $this->actingAs($owner)->postJson("/api/v1/favorites/{$salon->id}");

        $response->assertStatus(422);
    }
}
