<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\Service;
use App\Models\Appointment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WidgetSetting;
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
            'accepts_bookings' => true,
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

        $this->service = Service::factory()->create([
            'salon_id' => $this->salon->id,
            'duration' => 60,
            'price' => 50,
        ]);
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
            'guest_name' => 'Marko Markovic',
            'guest_email' => 'marko@example.com',
            'guest_phone' => '061234567',
            'guest_address' => 'Sarajevo',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('appointments', [
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'client_name' => 'Marko Markovic',
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
            'guest_name' => 'Marko Markovic',
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

    public function test_calendar_version_is_scoped_to_salon_owner(): void
    {
        $owner = User::factory()->create(['role' => 'salon']);
        $salon = Salon::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'approved',
        ]);
        $staff = Staff::factory()->create(['salon_id' => $salon->id]);
        $service = Service::factory()->create(['salon_id' => $salon->id]);
        $otherSalon = Salon::factory()->create(['status' => 'approved']);
        $otherStaff = Staff::factory()->create(['salon_id' => $otherSalon->id]);
        $otherService = Service::factory()->create(['salon_id' => $otherSalon->id]);
        $date = '2026-01-15';

        Appointment::factory()->create([
            'salon_id' => $salon->id,
            'staff_id' => $staff->id,
            'service_id' => $service->id,
            'date' => $date,
            'time' => '09:00',
            'end_time' => '09:30',
        ]);

        Appointment::factory()->create([
            'salon_id' => $otherSalon->id,
            'staff_id' => $otherStaff->id,
            'service_id' => $otherService->id,
            'date' => $date,
            'time' => '10:00',
            'end_time' => '10:30',
        ]);

        $response = $this->actingAs($owner)->getJson('/api/v1/appointments/calendar-version?start_date=15.01.2026&end_date=15.01.2026');

        $response->assertOk()
            ->assertJson([
                'count' => 1,
            ])
            ->assertJsonStructure([
                'enabled',
                'version',
                'latest_updated_at',
                'count',
            ]);
    }

    public function test_calendar_version_can_be_disabled_by_system_setting(): void
    {
        SystemSetting::set('calendar_realtime_refresh_enabled', false, 'boolean', 'performance');

        $owner = User::factory()->create(['role' => 'salon']);
        Salon::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($owner)->getJson('/api/v1/appointments/calendar-version?start_date=15.01.2026&end_date=15.01.2026');

        $response->assertOk()
            ->assertJson([
                'enabled' => false,
                'version' => null,
                'latest_updated_at' => null,
                'count' => null,
            ]);
    }

    public function test_admin_can_disable_calendar_realtime_refresh_setting(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->putJson('/api/v1/admin/settings', [
            'settings' => [
                [
                    'key' => 'calendar_realtime_refresh_enabled',
                    'value' => false,
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertFalse(SystemSetting::get('calendar_realtime_refresh_enabled', true));
        $this->assertDatabaseHas('system_settings', [
            'key' => 'calendar_realtime_refresh_enabled',
            'value' => 'false',
            'type' => 'boolean',
            'group' => 'performance',
        ]);
    }

    public function test_calendar_version_rejects_staff_filter_outside_owner_salon(): void
    {
        $owner = User::factory()->create(['role' => 'salon']);
        Salon::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'approved',
        ]);
        $otherSalon = Salon::factory()->create(['status' => 'approved']);
        $otherStaff = Staff::factory()->create(['salon_id' => $otherSalon->id]);

        $response = $this->actingAs($owner)->getJson(
            "/api/v1/appointments/calendar-version?start_date=15.01.2026&end_date=15.01.2026&staff_id={$otherStaff->id}"
        );

        $response->assertStatus(422);
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
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
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

        $response->assertStatus(409)
            ->assertJson(['code' => 'TIME_SLOT_TAKEN']);
    }

    public function test_cancelled_appointment_does_not_block_slot(): void
    {
        $tomorrow = now()->addDay()->format('d.m.Y');

        Appointment::factory()->create([
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => $tomorrow,
            'time' => '10:00',
            'end_time' => '11:00',
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($this->client)->postJson('/api/v1/appointments', [
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => $tomorrow,
            'time' => '10:00',
        ]);

        $response->assertStatus(201);
    }

    public function test_status_update_to_blocking_status_cannot_create_overlap(): void
    {
        $tomorrow = now()->addDay()->format('d.m.Y');
        $admin = User::factory()->create(['role' => 'admin']);

        Appointment::factory()->create([
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => $tomorrow,
            'time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        $cancelledAppointment = Appointment::factory()->create([
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => $tomorrow,
            'time' => '10:30',
            'end_time' => '11:30',
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($admin)->putJson("/api/v1/appointments/{$cancelledAppointment->id}", [
            'status' => 'confirmed',
        ]);

        $response->assertStatus(409)
            ->assertJson(['code' => 'TIME_SLOT_TAKEN']);
    }

    public function test_legacy_single_service_appointment_resource_fallback(): void
    {
        $appointment = Appointment::factory()->create([
            'client_id' => $this->client->id,
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'service_ids' => null,
        ]);

        $response = $this->actingAs($this->client)->getJson("/api/v1/appointments/{$appointment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.service_name', $this->service->name)
            ->assertJsonPath('data.services.0.id', $this->service->id);
    }

    public function test_public_guest_can_book_multiple_services(): void
    {
        $tomorrow = now()->addDay()->format('d.m.Y');
        $secondService = Service::factory()->create([
            'salon_id' => $this->salon->id,
            'duration' => 30,
            'price' => 25,
        ]);
        $this->staff->services()->attach($secondService->id);

        $response = $this->postJson('/api/v1/public/book', [
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'services' => [
                ['id' => $this->service->id],
                ['id' => $secondService->id],
            ],
            'date' => $tomorrow,
            'time' => '12:00',
            'guest_name' => 'Multi Service',
            'guest_email' => 'multi@example.com',
            'guest_phone' => '061234568',
            'guest_address' => 'Sarajevo',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('appointments', [
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => null,
            'client_name' => 'Multi Service',
        ]);
    }

    public function test_widget_booking_tracks_widget_source(): void
    {
        $tomorrow = now()->addDay()->format('d.m.Y');
        $widget = WidgetSetting::create([
            'salon_id' => $this->salon->id,
            'api_key' => 'test-widget-api-key',
            'is_active' => true,
            'allowed_domains' => [],
            'total_bookings' => 0,
        ]);

        $response = $this->postJson('/api/v1/widget/book', [
            'api_key' => $widget->api_key,
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => $tomorrow,
            'time' => '13:00',
            'guest_name' => 'Widget Guest',
            'guest_email' => 'widget@example.com',
            'guest_phone' => '061234569',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('appointments', [
            'client_name' => 'Widget Guest',
            'booking_source' => 'widget',
        ]);
        $this->assertSame(1, $widget->fresh()->total_bookings);
    }

    public function test_public_booking_idempotency_key_prevents_duplicate_retry(): void
    {
        $tomorrow = now()->addDay()->format('d.m.Y');
        $payload = [
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => $tomorrow,
            'time' => '14:00',
            'guest_name' => 'Retry Guest',
            'guest_email' => 'retry@example.com',
            'guest_phone' => '061234570',
            'idempotency_key' => 'public-retry-key',
        ];

        $this->postJson('/api/v1/public/book', $payload)->assertStatus(201);
        $this->postJson('/api/v1/public/book', $payload)->assertStatus(201);

        $this->assertSame(1, Appointment::where('client_name', 'Retry Guest')->count());
    }

    public function test_widget_booking_idempotency_key_does_not_double_count_analytics(): void
    {
        $tomorrow = now()->addDay()->format('d.m.Y');
        $widget = WidgetSetting::create([
            'salon_id' => $this->salon->id,
            'api_key' => 'test-widget-idempotency-key',
            'is_active' => true,
            'allowed_domains' => [],
            'total_bookings' => 0,
        ]);

        $payload = [
            'api_key' => $widget->api_key,
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => $tomorrow,
            'time' => '15:00',
            'guest_name' => 'Widget Retry',
            'guest_email' => 'widget-retry@example.com',
            'guest_phone' => '061234571',
            'idempotency_key' => 'widget-retry-key',
        ];

        $this->postJson('/api/v1/widget/book', $payload)->assertStatus(201);
        $this->postJson('/api/v1/widget/book', $payload)->assertStatus(201);

        $this->assertSame(1, Appointment::where('client_name', 'Widget Retry')->count());
        $this->assertSame(1, $widget->fresh()->total_bookings);
    }

    public function test_two_rapid_public_booking_attempts_for_same_slot_create_only_one_appointment(): void
    {
        $tomorrow = now()->addDay()->format('d.m.Y');
        $payload = [
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => $tomorrow,
            'time' => '16:00',
            'guest_name' => 'Fast Guest',
            'guest_phone' => '061234572',
        ];

        $this->postJson('/api/v1/public/book', $payload)->assertStatus(201);
        $this->postJson('/api/v1/public/book', array_merge($payload, [
            'guest_name' => 'Fast Guest Two',
            'guest_phone' => '061234573',
        ]))->assertStatus(409)
            ->assertJson(['code' => 'TIME_SLOT_TAKEN']);

        $this->assertSame(1, Appointment::where('staff_id', $this->staff->id)
            ->whereDate('date', now()->addDay()->format('Y-m-d'))
            ->where('time', '16:00')
            ->whereIn('status', Appointment::BLOCKING_STATUSES)
            ->count());
    }

    public function test_unavailable_slot_returns_precise_reason_code(): void
    {
        $tomorrow = now()->addDay()->format('d.m.Y');

        $response = $this->actingAs($this->client)->postJson('/api/v1/appointments', [
            'salon_id' => $this->salon->id,
            'staff_id' => $this->staff->id,
            'service_id' => $this->service->id,
            'date' => $tomorrow,
            'time' => '23:00',
        ]);

        $response->assertStatus(422)
            ->assertJson(['code' => 'OUTSIDE_WORKING_HOURS']);
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
