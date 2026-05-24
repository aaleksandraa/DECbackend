<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Review;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    protected Salon $salon;
    protected Staff $staff;
    protected Service $service;
    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salon = Salon::factory()->create([
            'status' => 'approved',
            'rating' => 0,
            'review_count' => 0,
        ]);

        $this->staff = Staff::factory()->create([
            'salon_id' => $this->salon->id,
            'rating' => 0,
            'review_count' => 0,
        ]);

        $this->service = Service::factory()->create([
            'salon_id' => $this->salon->id,
            'duration' => 60,
            'price' => 50,
        ]);

        $this->client = User::factory()->create(['role' => 'klijent']);
    }

    private function completedAppointment(?User $client = null, array $overrides = []): Appointment
    {
        $client ??= $this->client;

        return Appointment::factory()->create(array_merge([
            'client_id' => $client->id,
            'client_name' => $client->name,
            'client_email' => $client->email,
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => now()->subDay()->toDateString(),
            'time' => '10:00',
            'end_time' => '11:00',
            'status' => 'completed',
            'total_price' => 50,
        ], $overrides));
    }

    private function reviewForAppointment(Appointment $appointment, array $overrides = []): Review
    {
        return Review::factory()->create(array_merge([
            'client_id' => $appointment->client_id,
            'client_name' => $appointment->client_name,
            'salon_id' => $appointment->salon_id,
            'staff_id' => $appointment->staff_id,
            'appointment_id' => $appointment->id,
            'date' => now()->toDateString(),
        ], $overrides));
    }

    public function test_create_review_from_completed_appointment(): void
    {
        $appointment = $this->completedAppointment();

        $response = $this->actingAs($this->client)->postJson('/api/v1/reviews', [
            'appointment_id' => $appointment->id,
            'rating' => 5,
            'comment' => 'Odlican salon, preporucujem!',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reviews', [
            'salon_id' => $this->salon->id,
            'client_id' => $this->client->id,
            'appointment_id' => $appointment->id,
            'rating' => 5,
        ]);
    }

    public function test_review_validation_missing_rating(): void
    {
        $appointment = $this->completedAppointment();

        $response = $this->actingAs($this->client)->postJson('/api/v1/reviews', [
            'appointment_id' => $appointment->id,
            'comment' => 'Odlican salon, preporucujem!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('rating');
    }

    public function test_review_validation_invalid_rating_too_low(): void
    {
        $appointment = $this->completedAppointment();

        $response = $this->actingAs($this->client)->postJson('/api/v1/reviews', [
            'appointment_id' => $appointment->id,
            'rating' => 0,
            'comment' => 'Los salon',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('rating');
    }

    public function test_review_validation_invalid_rating_too_high(): void
    {
        $appointment = $this->completedAppointment();

        $response = $this->actingAs($this->client)->postJson('/api/v1/reviews', [
            'appointment_id' => $appointment->id,
            'rating' => 6,
            'comment' => 'Odlican salon',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('rating');
    }

    public function test_review_validation_comment_too_short(): void
    {
        $appointment = $this->completedAppointment();

        $response = $this->actingAs($this->client)->postJson('/api/v1/reviews', [
            'appointment_id' => $appointment->id,
            'rating' => 5,
            'comment' => 'OK',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('comment');
    }

    public function test_review_validation_comment_too_long(): void
    {
        $appointment = $this->completedAppointment();
        $longComment = str_repeat('a', 1001);

        $response = $this->actingAs($this->client)->postJson('/api/v1/reviews', [
            'appointment_id' => $appointment->id,
            'rating' => 5,
            'comment' => $longComment,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('comment');
    }

    public function test_user_cannot_create_duplicate_review_for_same_appointment(): void
    {
        $appointment = $this->completedAppointment();
        $this->reviewForAppointment($appointment);

        $response = $this->actingAs($this->client)->postJson('/api/v1/reviews', [
            'appointment_id' => $appointment->id,
            'rating' => 5,
            'comment' => 'Druga recenzija',
        ]);

        $response->assertStatus(422);
    }

    public function test_same_client_can_review_same_salon_again_from_another_completed_appointment(): void
    {
        $firstAppointment = $this->completedAppointment();
        $secondAppointment = $this->completedAppointment($this->client, [
            'date' => now()->subDays(2)->toDateString(),
            'time' => '12:00',
            'end_time' => '13:00',
        ]);
        $this->reviewForAppointment($firstAppointment);

        $response = $this->actingAs($this->client)->postJson('/api/v1/reviews', [
            'appointment_id' => $secondAppointment->id,
            'rating' => 4,
            'comment' => 'Druga posjeta je takodjer bila dobra.',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reviews', [
            'appointment_id' => $secondAppointment->id,
            'client_id' => $this->client->id,
            'rating' => 4,
        ]);
    }

    public function test_pending_or_confirmed_appointment_cannot_be_reviewed(): void
    {
        foreach (['pending' => '10:00', 'confirmed' => '12:00'] as $status => $time) {
            $appointment = $this->completedAppointment($this->client, [
                'status' => $status,
                'date' => now()->addDay()->toDateString(),
                'time' => $time,
                'end_time' => $time === '10:00' ? '11:00' : '13:00',
            ]);

            $response = $this->actingAs($this->client)->postJson('/api/v1/reviews', [
                'appointment_id' => $appointment->id,
                'rating' => 5,
                'comment' => 'Jos nije zavrseno',
            ]);

            $response->assertStatus(422);
        }
    }

    public function test_user_cannot_review_someone_elses_appointment(): void
    {
        $otherClient = User::factory()->create(['role' => 'klijent']);
        $appointment = $this->completedAppointment($otherClient);

        $response = $this->actingAs($this->client)->postJson('/api/v1/reviews', [
            'appointment_id' => $appointment->id,
            'rating' => 5,
            'comment' => 'Nije moj termin',
        ]);

        $response->assertStatus(403);
    }

    public function test_get_salon_reviews(): void
    {
        Review::factory()->count(5)->create(['salon_id' => $this->salon->id]);

        $response = $this->getJson("/api/v1/salons/{$this->salon->id}/reviews");

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    }

    public function test_review_pagination(): void
    {
        Review::factory()->count(15)->create(['salon_id' => $this->salon->id]);

        $response = $this->getJson("/api/v1/salons/{$this->salon->id}/reviews?per_page=5");

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    }

    public function test_update_own_review(): void
    {
        $appointment = $this->completedAppointment();
        $review = $this->reviewForAppointment($appointment, ['rating' => 3]);

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

    public function test_user_cannot_update_others_review(): void
    {
        $otherClient = User::factory()->create(['role' => 'klijent']);
        $appointment = $this->completedAppointment($otherClient);
        $review = $this->reviewForAppointment($appointment);

        $response = $this->actingAs($this->client)->putJson("/api/v1/reviews/{$review->id}", [
            'rating' => 1,
            'comment' => 'Hacked review',
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_own_review(): void
    {
        $appointment = $this->completedAppointment();
        $review = $this->reviewForAppointment($appointment);

        $response = $this->actingAs($this->client)->deleteJson("/api/v1/reviews/{$review->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_user_cannot_delete_others_review(): void
    {
        $otherClient = User::factory()->create(['role' => 'klijent']);
        $appointment = $this->completedAppointment($otherClient);
        $review = $this->reviewForAppointment($appointment);

        $response = $this->actingAs($this->client)->deleteJson("/api/v1/reviews/{$review->id}");

        $response->assertStatus(403);
    }

    public function test_salon_rating_calculation(): void
    {
        Review::factory()->create(['salon_id' => $this->salon->id, 'rating' => 5]);
        Review::factory()->create(['salon_id' => $this->salon->id, 'rating' => 4]);
        Review::factory()->create(['salon_id' => $this->salon->id, 'rating' => 3]);
        $this->salon->calculateRating();

        $response = $this->getJson("/api/v1/salons/{$this->salon->id}");

        $response->assertStatus(200);
        $this->assertEquals(4, $response->json('data.rating'));
    }

    public function test_review_sorting_by_rating_supports_old_and_new_query_params(): void
    {
        Review::factory()->create(['salon_id' => $this->salon->id, 'rating' => 3]);
        Review::factory()->create(['salon_id' => $this->salon->id, 'rating' => 5]);
        Review::factory()->create(['salon_id' => $this->salon->id, 'rating' => 4]);

        foreach (['sort=rating&direction=desc', 'order_by=rating&order_direction=desc'] as $query) {
            $response = $this->getJson("/api/v1/salons/{$this->salon->id}/reviews?{$query}");

            $response->assertStatus(200);
            $reviews = $response->json('data');
            $this->assertEquals(5, $reviews[0]['rating']);
            $this->assertEquals(4, $reviews[1]['rating']);
            $this->assertEquals(3, $reviews[2]['rating']);
        }
    }

    public function test_unauthenticated_user_cannot_create_review(): void
    {
        $appointment = $this->completedAppointment();

        $response = $this->postJson('/api/v1/reviews', [
            'appointment_id' => $appointment->id,
            'rating' => 5,
            'comment' => 'Great salon!',
        ]);

        $response->assertStatus(401);
    }

    public function test_review_with_all_valid_ratings(): void
    {
        for ($rating = 1; $rating <= 5; $rating++) {
            $client = User::factory()->create(['role' => 'klijent']);
            $appointment = $this->completedAppointment($client, [
                'date' => now()->subDays($rating)->toDateString(),
                'time' => sprintf('1%d:00', $rating),
                'end_time' => sprintf('1%d:30', $rating),
            ]);

            $response = $this->actingAs($client)->postJson('/api/v1/reviews', [
                'appointment_id' => $appointment->id,
                'rating' => $rating,
                'comment' => "Rating {$rating} comment",
            ]);

            $response->assertStatus(201);
        }

        $this->assertDatabaseCount('reviews', 5);
    }
}
