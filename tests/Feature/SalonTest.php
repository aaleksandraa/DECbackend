<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SalonTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test getting salon details
     */
    public function test_get_salon_details(): void
    {
        $salon = Salon::factory()->create([
            'name' => 'Test Salon',
            'status' => 'approved',
        ]);

        $response = $this->getJson("/api/v1/salons/{$salon->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Test Salon');
    }

    /**
     * Test salon not found returns 404
     */
    public function test_salon_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/v1/salons/99999');

        $response->assertStatus(404);
    }

    /**
     * Test getting salon services
     */
    public function test_get_salon_services(): void
    {
        $salon = Salon::factory()->create(['status' => 'approved']);

        $service1 = Service::factory()->create(['name' => 'Service 1']);
        $service2 = Service::factory()->create(['name' => 'Service 2']);

        $staff = Staff::factory()->create(['salon_id' => $salon->id]);
        $staff->services()->attach([$service1->id, $service2->id]);

        $response = $this->getJson("/api/v1/salons/{$salon->id}");

        $response->assertStatus(200);
        $services = $response->json('data.services');
        $this->assertGreaterThan(0, count($services));
    }

    /**
     * Test getting salon staff
     */
    public function test_get_salon_staff(): void
    {
        $salon = Salon::factory()->create(['status' => 'approved']);

        Staff::factory()->count(3)->create(['salon_id' => $salon->id]);

        $response = $this->getJson("/api/v1/salons/{$salon->id}");

        $response->assertStatus(200);
        $staff = $response->json('data.staff');
        $this->assertCount(3, $staff);
    }

    /**
     * Test salon profile update by owner
     */
    public function test_salon_owner_can_update_profile(): void
    {
        $user = User::factory()->create(['role' => 'salon']);
        $salon = Salon::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)->putJson("/api/v1/salons/{$salon->id}", [
            'name' => 'Updated Salon Name',
            'description' => 'Updated description',
            'phone' => '123456789',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('salons', [
            'id' => $salon->id,
            'name' => 'Updated Salon Name',
        ]);
    }

    /**
     * Test non-owner cannot update salon
     */
    public function test_non_owner_cannot_update_salon(): void
    {
        $owner = User::factory()->create(['role' => 'salon']);
        $other = User::factory()->create(['role' => 'salon']);
        $salon = Salon::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($other)->putJson("/api/v1/salons/{$salon->id}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test salon images upload
     */
    public function test_salon_images_upload(): void
    {
        $user = User::factory()->create(['role' => 'salon']);
        $salon = Salon::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/v1/salons/{$salon->id}/images", [
            'images' => [
                'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAA==',
            ],
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test salon working hours update
     */
    public function test_salon_working_hours_update(): void
    {
        $user = User::factory()->create(['role' => 'salon']);
        $salon = Salon::factory()->create(['owner_id' => $user->id]);

        $workingHours = [
            'monday' => ['open' => '08:00', 'close' => '18:00', 'is_open' => true],
            'tuesday' => ['open' => '08:00', 'close' => '18:00', 'is_open' => true],
            'wednesday' => ['open' => '08:00', 'close' => '18:00', 'is_open' => true],
            'thursday' => ['open' => '08:00', 'close' => '18:00', 'is_open' => true],
            'friday' => ['open' => '08:00', 'close' => '18:00', 'is_open' => true],
            'saturday' => ['open' => '09:00', 'close' => '17:00', 'is_open' => true],
            'sunday' => ['open' => '00:00', 'close' => '00:00', 'is_open' => false],
        ];

        $response = $this->actingAs($user)->putJson("/api/v1/salons/{$salon->id}", [
            'working_hours' => $workingHours,
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test getting salon reviews
     */
    public function test_get_salon_reviews(): void
    {
        $salon = Salon::factory()->create(['status' => 'approved']);

        $response = $this->getJson("/api/v1/salons/{$salon->id}/reviews");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'rating', 'comment', 'user_name']
            ]
        ]);
    }

    /**
     * Test salon search by location
     */
    public function test_salon_search_by_location(): void
    {
        Salon::factory()->create([
            'name' => 'Salon A',
            'city' => 'Sarajevo',
            'status' => 'approved',
        ]);

        Salon::factory()->create([
            'name' => 'Salon B',
            'city' => 'Mostar',
            'status' => 'approved',
        ]);

        $response = $this->getJson('/api/v1/public/search?city=Sarajevo');

        $response->assertStatus(200);
        $salons = $response->json('salons');
        $this->assertCount(1, $salons);
        $this->assertEquals('Sarajevo', $salons[0]['city']);
    }

    /**
     * Test salon target audience filter
     */
    public function test_salon_target_audience_filter(): void
    {
        Salon::factory()->create([
            'name' => 'Salon za Žene',
            'status' => 'approved',
            'target_audience' => ['women' => true, 'men' => false, 'children' => false],
        ]);

        Salon::factory()->create([
            'name' => 'Salon za Muškarce',
            'status' => 'approved',
            'target_audience' => ['women' => false, 'men' => true, 'children' => false],
        ]);

        $response = $this->getJson('/api/v1/public/search?audience=women');

        $response->assertStatus(200);
        $salons = $response->json('salons');
        $this->assertGreaterThan(0, count($salons));
    }

    /**
     * Test salon rating calculation
     */
    public function test_salon_rating_calculation(): void
    {
        $salon = Salon::factory()->create(['status' => 'approved']);

        $response = $this->getJson("/api/v1/salons/{$salon->id}");

        $response->assertStatus(200);
        $this->assertIsNumeric($response->json('data.rating'));
    }

    /**
     * Test salon with no reviews
     */
    public function test_salon_with_no_reviews(): void
    {
        $salon = Salon::factory()->create(['status' => 'approved']);

        $response = $this->getJson("/api/v1/salons/{$salon->id}");

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('data.review_count'));
    }
}
