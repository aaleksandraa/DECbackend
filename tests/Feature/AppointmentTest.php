<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\Service;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected Salon $salon;
    protected Staff $staff;
    protected Service $service;
    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestData();
    }

    private function createTestData(): void
    {
        $this->salon = Salon::factory()->create(['status' => 'approved']);

        $this->staff = Staff::factory()->create([
            'salon_id' => $this->salon->id,
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

        $this->service = Service::factory()->create(['duration' => 60, 'price' => 50]);
        $this->staff->services()->attach($this->service->id);

        $this->client = User::factory()->create(['role' => 'klijent']);
    }

    /**
     * Test creating appointment as guest
     */
    public function test_create_appointment_as_guest(): void
    {
        $tomorrow = now()->addDay()->format('d.m.Y');

        $response = $this->postJson('/api/v1/public/book', [
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => $tomorrow,
            'time' => '10:00',
            'guest_name' => 'Marko Marković',
            'guest_email' => 'marko@example.com',
            'guest_phone' => '061234567',
            'guest_address' => 'Sarajevo',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('appointments', [
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'guest_name' => 'Marko Marković',
        ]);
    }

    /**
     * Test guest appointment validation - missing name
     */
    public function test_guest_appointment_validation_missing_name(): void
    {
        $tomorrow = now()->addDay()->format('d.m.Y');

        $response = $this->postJson('/api/v1/public/book', [
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => $tomorrow,
            'time' => '10:00',
            'guest_phone' => '061234567',
            'guest_address' => 'Sarajevo',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('guest_name');
    }

    /**
     * Test guest appointment validation - invalid phone
     */
    public function test_guest_appointment_validation_invalid_phone(): void
    {
        $tomorrow = now()->addDay()->format('d.m.Y');

        $response = $this->postJson('/api/v1/public/book', [
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => $tomorrow,
            'time' => '10:00',
            'guest_name' => 'Marko Marković',
            'guest_phone' => 'invalid',
            'guest_address' => 'Sarajevo',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('guest_phone');
    }

    /**
     * Test creating appointment as authenticated client
     */
    public function test_create_appointment_as_client(): void
    {
        $tomorrow = now()->addDay()->format('d.m.Y');

        $response = $this->actingAs($this->client)->postJson('/api/v1/appointments', [
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => $tomorrow,
            'time' => '10:00',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('appointments', [
            'client_id' => $this->client->id,
            'salon_id' => $this->salon->id,
        ]);
    }

    /**
     * Test client cannot book outside working hours
     */
    public function test_client_cannot_book_outside_working_hours(): void
    {
        $tomorrow = now()->addDay()->format('d.m.Y');

        $response = $this->actingAs($this->client)->postJson('/api/v1/appointments', [
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => $tomorrow,
            'time' => '23:00',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test client cannot book on non-working day
     */
    public function test_client_cannot_book_on_non_working_day(): void
    {
        $sunday = now()->next('Sunday')->format('d.m.Y');

        $response = $this->actingAs($this->client)->postJson('/api/v1/appointments', [
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => $sunday,
            'time' => '10:00',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test client cannot book overlapping appointment
     */
    public function test_client_cannot_book_overlapping_appointment(): void
    {
        $tomorrow = now()->addDay()->format('d.m.Y');

        // Create first appointment
        Appointment::factory()->create([
            'staff_id' => $this->staff->id,
            'date' => $tomorrow,
            'time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        // Try to book overlapping time
        $response = $this->actingAs($this->client)->postJson('/api/v1/appointments', [
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => $tomorrow,
            'time' => '10:30',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test getting client appointments
     */
    public function test_get_client_appointments(): void
    {
        Appointment::factory()->count(3)->create([
            'client_id' => $this->client->id,
            'salon_id' => $this->salon->id,
        ]);

        $response = $this->actingAs($this->client)->getJson('/api/v1/appointments');

        $response->assertStatus(200);
        $appointments = $response->json('data');
        $this->assertCount(3, $appointments);
    }

    /**
     * Test getting single appointment
     */
    public function test_get_single_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->client)->getJson("/api/v1/appointments/{$appointment->id}");

        $response->assertStatus(200);
        $this->assertEquals($appointment->id, $response->json('data.id'));
    }

    /**
     * Test client cannot view other's appointment
     */
    public function test_client_cannot_view_others_appointment(): void
    {
        $otherClient = User::factory()->create(['role' => 'klijent']);
        $appointment = Appointment::factory()->create([
            'client_id' => $otherClient->id,
        ]);

        $response = $this->actingAs($this->client)->getJson("/api/v1/appointments/{$appointment->id}");

        $response->assertStatus(403);
    }

    /**
     * Test cancelling appointment
     */
    public function test_cancel_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'client_id' => $this->client->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->client)->putJson("/api/v1/appointments/{$appointment->id}/cancel");

        $response->assertStatus(200);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'cancelled',
        ]);
    }

    /**
     * Test cannot cancel already cancelled appointment
     */
    public function test_cannot_cancel_already_cancelled_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'client_id' => $this->client->id,
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($this->client)->putJson("/api/v1/appointments/{$appointment->id}/cancel");

        $response->assertStatus(422);
    }

    /**
     * Test salon owner can view all appointments
     */
    public function test_salon_owner_can_view_appointments(): void
    {
        $owner = User::factory()->create(['role' => 'salon']);
        $salon = Salon::factory()->create(['owner_id' => $owner->id]);

        Appointment::factory()->count(5)->create(['salon_id' => $salon->id]);

        $response = $this->actingAs($owner)->getJson("/api/v1/appointments");

        $response->assertStatus(200);
        $appointments = $response->json('data');
        $this->assertGreaterThanOrEqual(5, count($appointments));
    }

    /**
     * Test appointment date format validation
     */
    public function test_appointment_date_format_validation(): void
    {
        $response = $this->actingAs($this->client)->postJson('/api/v1/appointments', [
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => 'invalid-date',
            'time' => '10:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('date');
    }

    /**
     * Test appointment time format validation
     */
    public function test_appointment_time_format_validation(): void
    {
        $tomorrow = now()->addDay()->format('d.m.Y');

        $response = $this->actingAs($this->client)->postJson('/api/v1/appointments', [
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => $tomorrow,
            'time' => 'invalid-time',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('time');
    }
}
