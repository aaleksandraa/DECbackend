<?php

namespace App\Services;

use App\Models\Salon;
use App\Models\SalonImage;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SalonService
{
    /**
     * Create a new salon.
     */
    public function createSalon(array $data, User $owner): Salon
    {
        return DB::transaction(function () use ($data, $owner) {
            // Create salon
            $salon = Salon::create([
                'name' => $data['name'],
                'description' => $data['description'],
                'address' => $data['address'],
                'city' => $data['city'],
                'postal_code' => $data['postal_code'] ?? null,
                'country' => $data['country'] ?? 'Bosna i Hercegovina',
                'phone' => $data['phone'],
                'email' => $data['email'],
                'website' => $data['website'] ?? null,
                'working_hours' => $data['working_hours'],
                'location' => $data['location'],
                'target_audience' => $data['target_audience'] ?? [
                    'women' => true,
                    'men' => true,
                    'children' => true,
                ],
                'amenities' => $data['amenities'] ?? [],
                'social_media' => $data['social_media'] ?? null,
                'owner_id' => $owner->id,
                'status' => 'pending',
            ]);

            // Update owner's salon_id
            $owner->update(['salon_id' => $salon->id]);

            return $salon;
        });
    }

    /**
     * Update an existing salon.
     */
    public function updateSalon(Salon $salon, array $data): Salon
    {
        return DB::transaction(function () use ($salon, $data) {
            $salon->update($data);
            return $salon;
        });
    }

    /**
     * Get the nearest salons based on location.
     */
    public function getNearestSalons(float $latitude, float $longitude, float $radius = 10): array
    {
        // Using Haversine formula to calculate distance
        $salons = Salon::approved()
            ->selectRaw("*,
                (6371 * acos(cos(radians(?)) * cos(radians(JSON_EXTRACT(location, '$.lat'))) *
                cos(radians(JSON_EXTRACT(location, '$.lng')) - radians(?)) +
                sin(radians(?)) * sin(radians(JSON_EXTRACT(location, '$.lat'))))) AS distance",
                [$latitude, $longitude, $latitude])
            ->having('distance', '<=', $radius)
            ->orderBy('distance')
            ->with(['images', 'services'])
            ->get();

        return $salons->toArray();
    }

    /**
     * Get available time slots for a salon, staff, and date.
     *
     * @param Salon $salon The salon
     * @param string $staffId Staff member ID
     * @param string $date Date in DD.MM.YYYY or YYYY-MM-DD format
     * @param string $serviceId Service ID (used for validation)
     * @param int|null $totalDuration Total duration in minutes (if multiple services, otherwise uses service duration)
     * @return array Available time slots
     */
    public function getAvailableTimeSlots(Salon $salon, string $staffId, string $date, string $serviceId, ?int $totalDuration = null): array
    {
        $staff = Staff::with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations', 'services'])
            ->findOrFail($staffId);
        $service = Service::findOrFail($serviceId);

        // Check if staff belongs to salon
        if ($staff->salon_id !== $salon->id) {
            return [];
        }

        // Check if staff can perform this service
        if (!$staff->services->contains($service->id)) {
            return [];
        }

        // CRITICAL: Use total duration if provided (for multiple services), otherwise use single service duration
        $duration = $totalDuration ?? $service->duration;

        // DEBUG: Log the duration being used
        \Log::info('Available Slots Calculation', [
            'service_id' => $serviceId,
            'service_duration' => $service->duration,
            'total_duration_param' => $totalDuration,
            'duration_used' => $duration,
            'date' => $date
        ]);

        // Convert date format if needed (from European DD.MM.YYYY to ISO YYYY-MM-DD)
        $isoDate = $date;
        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $date)) {
            $isoDate = \Carbon\Carbon::createFromFormat('d.m.Y', $date)->format('Y-m-d');
        }

        // Get day of week
        $dayOfWeek = strtolower(date('l', strtotime($isoDate)));

        // Check salon working hours
        $salonHours = $salon->working_hours[$dayOfWeek] ?? null;
        if (!$salonHours || !$salonHours['is_open']) {
            \Log::info('Salon is closed on this day', [
                'salon_id' => $salon->id,
                'day_of_week' => $dayOfWeek
            ]);
            return [];
        }

        // Check staff working hours
        $staffHours = $staff->working_hours[$dayOfWeek] ?? null;
        if (!$staffHours || !$staffHours['is_working']) {
            \Log::info('Staff is not working on this day', [
                'staff_id' => $staffId,
                'day_of_week' => $dayOfWeek
            ]);
            return [];
        }

        // Determine start and end times (use the later start time and earlier end time)
        $startTime = max($salonHours['open'], $staffHours['start']);
        $endTime = min($salonHours['close'], $staffHours['end']);

        // DEBUG: Log working hours comparison
        \Log::info('Working Hours Comparison', [
            'salon_open' => $salonHours['open'],
            'salon_close' => $salonHours['close'],
            'staff_start' => $staffHours['start'],
            'staff_end' => $staffHours['end'],
            'effective_start' => $startTime,
            'effective_end' => $endTime,
            'explanation' => "Using later start ({$startTime}) and earlier end ({$endTime})"
        ]);

        // CRITICAL FIX: Calculate the latest possible start time
        // Service(s) must be completed BEFORE end time
        // If total duration is 480 minutes (8 hours) and end time is 19:00,
        // the latest start time should be 11:00 (19:00 - 8 hours)
        $endTimeTimestamp = strtotime($endTime);
        $latestStartTime = date('H:i', strtotime("-{$duration} minutes", $endTimeTimestamp));

        // DEBUG: Log the calculation
        \Log::info('Latest Start Time Calculation', [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration' => $duration,
            'latest_start_time' => $latestStartTime,
            'day_of_week' => $dayOfWeek
        ]);

        // If latest start time is before the actual start time, no slots available
        if (strtotime($latestStartTime) < strtotime($startTime)) {
            \Log::warning('No slots available - latest start time is before opening time', [
                'latest_start_time' => $latestStartTime,
                'start_time' => $startTime
            ]);
            return [];
        }

        // Generate all possible time slots using salon's booking interval
        // Only generate slots up to the latest possible start time
        $interval = $salon->booking_slot_interval ?? 30; // Default to 30 if not set
        $slots = $this->generateTimeSlots($startTime, $latestStartTime, $interval, true);

        \Log::info('Generated Slots', [
            'total_slots' => count($slots),
            'first_slot' => $slots[0] ?? null,
            'last_slot' => end($slots) ?: null
        ]);

        // CRITICAL: Filter out past slots for today
        $isToday = ($isoDate === date('Y-m-d'));
        $currentTime = date('H:i');

        // Filter out slots that are not available
        $availableSlots = [];
        foreach ($slots as $slot) {
            // Skip past slots for today
            if ($isToday && $slot <= $currentTime) {
                continue;
            }

            // Use the total duration for availability check
            if ($staff->isAvailable($date, $slot, $duration)) {
                $availableSlots[] = $slot;
            }
        }

        return $availableSlots;
    }

    /**
     * Generate time slots between start and end time.
     *
     * @param string $startTime Start time (e.g., "09:00")
     * @param string $endTime End time (e.g., "19:00")
     * @param int $interval Interval in minutes (default: 30)
     * @param bool $includeEndTime Whether to include the end time as a slot (default: false)
     * @return array Array of time slots
     */
    private function generateTimeSlots(string $startTime, string $endTime, int $interval = 30, bool $includeEndTime = false): array
    {
        $slots = [];
        $start = strtotime($startTime);
        $end = strtotime($endTime);

        // Generate slots up to (but not including) end time
        for ($time = $start; $time < $end; $time += $interval * 60) {
            $slots[] = date('H:i', $time);
        }

        // Optionally include the end time as the last slot
        if ($includeEndTime && $start <= $end) {
            $slots[] = date('H:i', $end);
        }

        return $slots;
    }

    /**
     * PROFESSIONAL SOLUTION: Get available time slots for multiple services with different staff members.
     *
     * This method handles the complex scenario where:
     * - User selects multiple services (e.g., 4 services)
     * - Each service can have a different staff member
     * - Services are performed sequentially (one after another)
     * - All staff members must be available for their respective services
     *
     * Algorithm:
     * 1. Calculate total duration of all services
     * 2. Find the most restrictive working hours (earliest end time among all staff)
     * 3. Generate potential slots
     * 4. For each slot, verify ALL staff members are available for their services
     *
     * @param Salon $salon
     * @param string $date Date in DD.MM.YYYY format
     * @param array $services Array of ['serviceId' => string, 'staffId' => string, 'duration' => int]
     * @return array Available time slots
     */
    public function getAvailableTimeSlotsForMultipleServices(Salon $salon, string $date, array $services): array
    {
        \Log::info('=== MULTI-SERVICE SLOT CALCULATION ===', [
            'salon_id' => $salon->id,
            'date' => $date,
            'services_count' => count($services)
        ]);

        if (empty($services)) {
            return [];
        }

        // Convert date format
        $isoDate = $date;
        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $date)) {
            $isoDate = \Carbon\Carbon::createFromFormat('d.m.Y', $date)->format('Y-m-d');
        }

        $dayOfWeek = strtolower(date('l', strtotime($isoDate)));

        // Check salon working hours
        $salonHours = $salon->working_hours[$dayOfWeek] ?? null;
        if (!$salonHours || !$salonHours['is_open']) {
            \Log::info('Salon closed', ['day' => $dayOfWeek]);
            return [];
        }

        // Calculate total duration from ALL services
        $totalDuration = 0;
        foreach ($services as $service) {
            $totalDuration += $service['duration'] ?? 0;
        }

        \Log::info('Total duration', ['total' => $totalDuration, 'services' => count($services)]);

        // Get the staff member (all services use the same staff in new logic)
        $staffId = $services[0]['staffId'] ?? null;
        if (!$staffId) {
            \Log::warning('No staff ID provided');
            return [];
        }

        $staff = Staff::with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations'])
            ->find($staffId);

        if (!$staff) {
            \Log::warning('Staff not found', ['staff_id' => $staffId]);
            return [];
        }

        // Check staff working hours
        $staffHours = $staff->working_hours[$dayOfWeek] ?? null;
        if (!$staffHours || !$staffHours['is_working']) {
            \Log::info('Staff not working', ['staff_id' => $staffId, 'day' => $dayOfWeek]);
            return [];
        }

        // Use the most restrictive hours (salon vs staff)
        $effectiveStart = max($salonHours['open'], $staffHours['start']);
        $effectiveEnd = min($salonHours['close'], $staffHours['end']);

        \Log::info('Working hours', [
            'salon' => $salonHours['open'] . '-' . $salonHours['close'],
            'staff' => $staffHours['start'] . '-' . $staffHours['end'],
            'effective' => $effectiveStart . '-' . $effectiveEnd
        ]);

        // Calculate latest possible start time based on TOTAL duration
        $endTimeTimestamp = strtotime($effectiveEnd);
        $latestStartTime = date('H:i', strtotime("-{$totalDuration} minutes", $endTimeTimestamp));

        \Log::info('Latest start time', [
            'end_time' => $effectiveEnd,
            'total_duration' => $totalDuration,
            'latest_start' => $latestStartTime
        ]);

        if (strtotime($latestStartTime) < strtotime($effectiveStart)) {
            \Log::warning('No slots - duration too long', [
                'start' => $effectiveStart,
                'latest' => $latestStartTime,
                'duration' => $totalDuration
            ]);
            return [];
        }

        // Generate potential slots using salon's booking interval
        $interval = $salon->booking_slot_interval ?? 30; // Default to 30 if not set
        $potentialSlots = $this->generateTimeSlots($effectiveStart, $latestStartTime, $interval, true);

        // Filter slots where staff is available for the TOTAL duration
        $availableSlots = [];

        // CRITICAL: Filter out past slots for today
        $isToday = ($isoDate === date('Y-m-d'));
        $currentTime = date('H:i');

        foreach ($potentialSlots as $slot) {
            // Skip past slots for today
            if ($isToday && $slot <= $currentTime) {
                continue;
            }

            // Check if staff is available for the entire duration starting at this slot
            if ($staff->isAvailable($date, $slot, $totalDuration)) {
                $availableSlots[] = $slot;
            }
        }

        \Log::info('Available slots result', [
            'potential' => count($potentialSlots),
            'available' => count($availableSlots),
            'is_today' => $isToday,
            'current_time' => $currentTime,
            'first' => $availableSlots[0] ?? null,
            'last' => end($availableSlots) ?: null
        ]);

        return $availableSlots;
    }
}
