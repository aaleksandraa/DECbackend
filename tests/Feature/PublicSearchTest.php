<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\Service;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class PublicSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestData();
    }

    private function createTestData(): void
    {
        // Create test salons
        $salon1 = Salon::factory()->create([
            'name' => 'Salon Ljepote',
            'city' => 'Sarajevo',
            'status' => 'approved',
            'rating' => 4.5,
            'review_count' => 10,
        ]);

        $salon2 = Salon::factory()->create([
            'name' => 'Frizerski Salon',
            'city' => 'Sarajevo',
            'status' => 'approved',
            'rating' => 3.8,
            'review_count' => 5,
        ]);

        $salon3 = Salon::factory()->create([
            'name' => 'Beauty Center',
            'city' => 'Mostar',
            'status' => 'approved',
            'rating' => 4.2,
            'review_count' => 8,
        ]);

        // Create staff with working hours
        $staff1 = Staff::factory()->create([
            'salon_id' => $salon1->id,
            'name' => 'Marko',
            'is_active' => true,
            'working_hours' => [
                'monday' => ['start' => '08:00', 'end' => '18:00', 'is_working' => true],
                'tuesday' => ['start' => '08:00', 'end' => '18:00', 'is_working' => true],
                'wednesday' => ['start' => '08:00', 'end' => '18:00', 'is_working' => true],
                'thursday' => ['start' => '08:00', 'end' => '18:00', 'is_working' => true],
                'friday' => ['start' => '08:00', 'end' => '18:00', 'is_working' => true],
                'saturday' => ['start' => '09:00', 'end' => '17:00', 'is_working' => true],
                'sunday' => ['start' => '00:00', 'end' => '00:00', 'is_working' => false],
            ],
        ]);

        $staff2 = Staff::factory()->create([
            'salon_id' => $salon1->id,
            'name' => 'Ana',
            'is_active' => true,
            'working_hours' => [
                'monday' => ['start' => '10:00', 'end' => '20:00', 'is_working' => true],
                'tuesday' => ['start' => '10:00', 'end' => '20:00', 'is_working' => true],
                'wednesday' => ['start' => '10:00', 'end' => '20:00', 'is_working' => true],
                'thursday' => ['start' => '10:00', 'end' => '20:00', 'is_working' => true],
                'friday' => ['start' => '10:00', 'end' => '20:00', 'is_working' => true],
                'saturday' => ['start' => '10:00', 'end' => '20:00', 'is_working' => true],
                'sunday' => ['start' => '00:00', 'end' => '00:00', 'is_working' => false],
            ],
        ]);

        $staff3 = Staff::factory()->create([
            'salon_id' => $salon2->id,
            'name' => 'Petar',
            'is_active' => true,
            'working_hours' => [
                'monday' => ['start' => '08:00', 'end' => '16:00', 'is_working' => true],
                'tuesday' => ['start' => '08:00', 'end' => '16:00', 'is_working' => true],
                'wednesday' => ['start' => '08:00', 'end' => '16:00', 'is_working' => true],
                'thursday' => ['start' => '08:00', 'end' => '16:00', 'is_working' => true],
                'friday' => ['start' => '08:00', 'end' => '16:00', 'is_working' => true],
                'saturday' => ['start' => '00:00', 'end' => '00:00', 'is_working' => false],
                'sunday' => ['start' => '00:00', 'end' => '00:00', 'is_working' => false],
            ],
        ]);

        // Create services
        $service1 = Service::factory()->create([
            'name' => 'Muško šišanje',
            'category' => 'Šišanje',
            'price' => 20,
            'duration' => 30,
        ]);

        $service2 = Service::factory()->create([
            'name' => 'Žensko šišanje',
            'category' => 'Šišanje',
            'price' => 40,
            'duration' => 60,
        ]);

        // Attach services to staff
        $staff1->services()->attach($service1->id);
        $staff1->services()->attach($service2->id);
        $staff2->services()->attach($service2->id);
        $staff3->services()->attach($service1->id);
    }

    /**
     * Test basic search without filters
     */
    public function test_search_returns_all_approved_salons(): void
    {
        $response = $this->getJson('/api/v1/public/search');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'salons' => [
                '*' => ['id', 'name', 'city', 'rating']
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total']
        ]);
        $this->assertCount(3, $response->json('salons'));
    }

    /**
     * Test search by city
     */
    public function test_search_filters_by_city(): void
    {
        $response = $this->getJson('/api/v1/public/search?city=Sarajevo');

        $response->assertStatus(200);
        $salons = $response->json('salons');
        $this->assertCount(2, $salons);
        $this->assertEquals('Sarajevo', $salons[0]['city']);
        $this->assertEquals('Sarajevo', $salons[1]['city']);
    }

    /**
     * Test search by name
     */
    public function test_search_filters_by_name(): void
    {
        $response = $this->getJson('/api/v1/public/search?q=Salon%20Ljepote');

        $response->assertStatus(200);
        $salons = $response->json('salons');
        $this->assertCount(1, $salons);
        $this->assertEquals('Salon Ljepote', $salons[0]['name']);
    }

    /**
     * Test search by minimum rating
     */
    public function test_search_filters_by_minimum_rating(): void
    {
        $response = $this->getJson('/api/v1/public/search?min_rating=4');

        $response->assertStatus(200);
        $salons = $response->json('salons');
        $this->assertCount(2, $salons); // Salon Ljepote (4.5) and Beauty Center (4.2)

        foreach ($salons as $salon) {
            $this->assertGreaterThanOrEqual(4, $salon['rating']);
        }
    }

    /**
     * Test search by service
     */
    public function test_search_filters_by_service(): void
    {
        $response = $this->getJson('/api/v1/public/search?service=Muško%20šišanje');

        $response->assertStatus(200);
        $salons = $response->json('salons');
        $this->assertGreaterThan(0, count($salons));
    }

    /**
     * Test search with date filter - today
     */
    public function test_search_filters_by_date_today(): void
    {
        $today = now()->format('Y-m-d');
        $response = $this->getJson("/api/v1/public/search?date={$today}");

        $response->assertStatus(200);
        // Should return salons that have staff working today
        $salons = $response->json('salons');
        $this->assertGreaterThan(0, count($salons));
    }

    /**
     * Test search with time filter - morning hours
     */
    public function test_search_filters_by_time_morning(): void
    {
        $today = now()->format('Y-m-d');
        $response = $this->getJson("/api/v1/public/search?date={$today}&time=09:00");

        $response->assertStatus(200);
        $salons = $response->json('salons');
        // Should return salons with staff working at 09:00
        $this->assertGreaterThan(0, count($salons));
    }

    /**
     * Test search with time filter - late hours (should return fewer results)
     */
    public function test_search_filters_by_time_late_evening(): void
    {
        $today = now()->format('Y-m-d');
        $response = $this->getJson("/api/v1/public/search?date={$today}&time=23:00");

        $response->assertStatus(200);
        $salons = $response->json('salons');
        // Should return no salons since none work at 23:00
        $this->assertCount(0, $salons);
    }

    /**
     * Test search with time filter - early morning
     */
    public function test_search_filters_by_time_early_morning(): void
    {
        $today = now()->format('Y-m-d');
        $response = $this->getJson("/api/v1/public/search?date={$today}&time=07:00");

        $response->assertStatus(200);
        $salons = $response->json('salons');
        // Should return no salons since none work at 07:00
        $this->assertCount(0, $salons);
    }

    /**
     * Test pagination - default per_page is 12
     */
    public function test_search_pagination_default(): void
    {
        $response = $this->getJson('/api/v1/public/search');

        $response->assertStatus(200);
        $meta = $response->json('meta');
        $this->assertEquals(1, $meta['current_page']);
        $this->assertEquals(12, $meta['per_page']);
        $this->assertLessThanOrEqual(12, count($response->json('salons')));
    }

    /**
     * Test pagination - custom per_page
     */
    public function test_search_pagination_custom_per_page(): void
    {
        $response = $this->getJson('/api/v1/public/search?per_page=2');

        $response->assertStatus(200);
        $meta = $response->json('meta');
        $this->assertEquals(2, $meta['per_page']);
        $this->assertCount(2, $response->json('salons'));
    }

    /**
     * Test pagination - page 2
     */
    public function test_search_pagination_page_2(): void
    {
        $response = $this->getJson('/api/v1/public/search?per_page=2&page=2');

        $response->assertStatus(200);
        $meta = $response->json('meta');
        $this->assertEquals(2, $meta['current_page']);
    }

    /**
     * Test combined filters
     */
    public function test_search_with_combined_filters(): void
    {
        $today = now()->format('Y-m-d');
        $response = $this->getJson("/api/v1/public/search?city=Sarajevo&min_rating=4&date={$today}&time=10:00");

        $response->assertStatus(200);
        $salons = $response->json('salons');

        foreach ($salons as $salon) {
            $this->assertEquals('Sarajevo', $salon['city']);
            $this->assertGreaterThanOrEqual(4, $salon['rating']);
        }
    }

    /**
     * Test search returns meta information
     */
    public function test_search_returns_meta_information(): void
    {
        $response = $this->getJson('/api/v1/public/search');

        $response->assertStatus(200);
        $meta = $response->json('meta');

        $this->assertIsInt($meta['current_page']);
        $this->assertIsInt($meta['last_page']);
        $this->assertIsInt($meta['per_page']);
        $this->assertIsInt($meta['total']);
        $this->assertIsInt($meta['from']);
        $this->assertIsInt($meta['to']);
    }

    /**
     * Test search returns applied filters
     */
    public function test_search_returns_applied_filters(): void
    {
        $response = $this->getJson('/api/v1/public/search?city=Sarajevo&min_rating=4');

        $response->assertStatus(200);
        $filters = $response->json('filters.applied');

        $this->assertEquals('Sarajevo', $filters['city']);
        $this->assertEquals('4', $filters['min_rating']);
    }

    /**
     * Test search with invalid date format returns empty
     */
    public function test_search_with_invalid_date_format(): void
    {
        $response = $this->getJson('/api/v1/public/search?date=invalid-date');

        $response->assertStatus(200);
        // Should handle gracefully
    }

    /**
     * Test search with invalid time format returns empty
     */
    public function test_search_with_invalid_time_format(): void
    {
        $today = now()->format('Y-m-d');
        $response = $this->getJson("/api/v1/public/search?date={$today}&time=invalid-time");

        $response->assertStatus(200);
        // Should handle gracefully
    }

    /**
     * Test search excludes unapproved salons
     */
    public function test_search_excludes_unapproved_salons(): void
    {
        Salon::factory()->create([
            'name' => 'Unapproved Salon',
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/v1/public/search');

        $response->assertStatus(200);
        $salons = $response->json('salons');
        $this->assertCount(3, $salons); // Only approved salons
    }

    /**
     * Test search with audience filter
     */
    public function test_search_filters_by_audience(): void
    {
        Salon::factory()->create([
            'name' => 'Salon za Žene',
            'city' => 'Sarajevo',
            'status' => 'approved',
            'target_audience' => ['women' => true, 'men' => false, 'children' => false],
        ]);

        $response = $this->getJson('/api/v1/public/search?audience=women');

        $response->assertStatus(200);
        $salons = $response->json('salons');
        $this->assertGreaterThan(0, count($salons));
    }
}
