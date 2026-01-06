<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\Service;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class AppointmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AppointmentService $appointmentService;
    protected Salon $salon;
    protected Staff $staff;
    protected Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appointmentService = app(AppointmentService::class);
        $this->createTestData();
    }

    private function createTestData(): void
    {
        $this->salon = Salon::factory()->create([
            'name' => 'Test Salon',
            'status' => 'approved',
        ]);

        $this->staff = Staff::factory()->create([
            'salon_id' => $this->salon->id,
            'name' => 'Test Staff',
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

        $this->service = Service::factory()->create([
            'name' => 'Test Service',
            'price' => 50,
            'duration' => 60,
        ]);

        $this->staff->services()->attach($this->service->id);
    }

    /**
     * Test checking if staff is available at specific time
     */
    public function test_staff_available_during_working_hours(): void
    {
        $tomorrow = now()->addDay()->format('Y-m-d');
        $result = $this->appointmentService->isStaffAvailable(
            $this->staff,
            $tomorrow,
            '10:00',
            60
        );

        $this->assertTrue($result);
    }

    /**
     * Test staff not available before working hours
     */
    public function test_staff_not_available_before_working_hours(): void
    {
        $tomorrow = now()->addDay()->format('Y-m-d');
        $result = $this->appointmentService->isStaffAvailable(
            $this->staff,
            $tomorrow,
            '07:00',
            60
        );

        $this->assertFalse($result);
    }

    /**
     * Test staff not available after working hours
     */
    public function test_staff_not_available_after_working_hours(): void
    {
        $tomorrow = now()->addDay()->format('Y-m-d');
        $result = $this->appointmentService->isStaffAvailable(
            $this->staff,
            $tomorrow,
            '17:30',
            60
        );

        $this->assertFalse($result);
    }

    /**
     * Test staff not available on non-working day
     */
    public function test_staff_not_available_on_non_working_day(): void
    {
        // Find next Sunday
        $sunday = now()->next('Sunday')->format('Y-m-d');
        $result = $this->appointmentService->isStaffAvailable(
            $this->staff,
            $sunday,
            '10:00',
            60
        );

        $this->assertFalse($result);
    }

    /**
     * Test staff not available when appointment would exceed working hours
     */
    public function test_staff_not_available_when_appointment_exceeds_working_hours(): void
    {
        $tomorrow = now()->addDay()->format('Y-m-d');
        // Try to book at 17:30 with 60 minute duration (would end at 18:30, but work ends at 18:00)
        $result = $this->appointmentService->isStaffAvailable(
            $this->staff,
            $tomorrow,
            '17:30',
            60
        );

        $this->assertFalse($result);
    }

    /**
     * Test staff not available when there's an existing appointment
     */
    public function test_staff_not_available_when_appointment_exists(): void
    {
        $tomorrow = now()->addDay()->format('Y-m-d');

        // Create existing appointment
        Appointment::factory()->create([
            'staff_id' => $this->staff->id,
            'date' => $tomorrow,
            'time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        // Try to book overlapping time
        $result = $this->appointmentService->isStaffAvailable(
            $this->staff,
            $tomorrow,
            '10:30',
            60
        );

        $this->assertFalse($result);
    }

    /**
     * Test staff available when appointment doesn't overlap
     */
    public function test_staff_available_when_appointment_does_not_overlap(): void
    {
        $tomorrow = now()->addDay()->format('Y-m-d');

        // Create existing appointment
        Appointment::factory()->create([
            'staff_id' => $this->staff->id,
            'date' => $tomorrow,
            'time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        // Try to book non-overlapping time
        $result = $this->appointmentService->isStaffAvailable(
            $this->staff,
            $tomorrow,
            '11:00',
            60
        );

        $this->assertTrue($result);
    }

    /**
     * Test getting available salon IDs for a date
     */
    public function test_get_available_salon_ids_for_date(): void
    {
        $tomorrow = now()->addDay()->format('Y-m-d');
        $availableSalonIds = $this->appointmentService->getAvailableSalonIds($tomorrow);

        $this->assertIsArray($availableSalonIds);
        $this->assertContains($this->salon->id, $availableSalonIds);
    }

    /**
     * Test getting available salon IDs for specific time
     */
    public function test_get_available_salon_ids_for_specific_time(): void
    {
        $tomorrow = now()->addDay()->format('Y-m-d');
        $availableSalonIds = $this->appointmentService->getAvailableSalonIds($tomorrow, '10:00', 60);

        $this->assertIsArray($availableSalonIds);
        $this->assertContains($this->salon->id, $availableSalonIds);
    }

    /**
     * Test no available salons for late evening time
     */
    public function test_no_available_salons_for_late_evening(): void
    {
        $tomorrow = now()->addDay()->format('Y-m-d');
        $availableSalonIds = $this->appointmentService->getAvailableSalonIds($tomorrow, '23:00', 60);

        $this->assertIsArray($availableSalonIds);
        $this->assertEmpty($availableSalonIds);
    }

    /**
     * Test getting available slots for staff
     */
    public function test_get_available_slots_for_staff(): void
    {
        $tomorrow = now()->addDay()->format('Y-m-d');
        $slots = $this->appointmentService->getAvailableSlots($this->staff, $tomorrow, 60);

        $this->assertIsArray($slots);
        $this->assertGreaterThan(0, count($slots));

        // All slots should be within working hours
        foreach ($slots as $slot) {
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $slot);
        }
    }

    /**
     * Test available slots don't include past times for today
     */
    public function test_available_slots_exclude_past_times_for_today(): void
    {
        $today = now()->format('Y-m-d');
        $slots = $this->appointmentService->getAvailableSlots($this->staff, $today, 60);

        $now = now()->format('H:i');
        foreach ($slots as $slot) {
            $this->assertGreaterThan($now, $slot);
        }
    }

    /**
     * Test salon availability check
     */
    public function test_salon_availability_check(): void
    {
        $tomorrow = now()->addDay()->format('Y-m-d');
        $result = $this->appointmentService->isSalonAvailable(
            $this->salon->id,
            $tomorrow,
            '10:00',
            60
        );

        $this->assertTrue($result);
    }

    /**
     * Test salon not available when no staff working
     */
    public function test_salon_not_available_when_no_staff_working(): void
    {
        $sunday = now()->next('Sunday')->format('Y-m-d');
        $result = $this->appointmentService->isSalonAvailable(
            $this->salon->id,
            $sunday,
            '10:00',
            60
        );

        $this->assertFalse($result);
    }

    /**
     * Test date conversion from DD.MM.YYYY to ISO format
     */
    public function test_date_conversion_from_european_format(): void
    {
        $europeanDate = '25.12.2024';
        $isoDate = $this->appointmentService->toIsoDate($europeanDate);

        $this->assertEquals('2024-12-25', $isoDate);
    }

    /**
     * Test date conversion from YYYY-MM-DD stays same
     */
    public function test_date_conversion_from_iso_format(): void
    {
        $isoDate = '2024-12-25';
        $result = $this->appointmentService->toIsoDate($isoDate);

        $this->assertEquals('2024-12-25', $result);
    }
}
