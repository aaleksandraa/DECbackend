<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use Carbon\Carbon;

class AppointmentService
{
    /**
     * Calculate the end time for an appointment.
     */
    public function calculateEndTime(string $startTime, int $duration): string
    {
        $startTimestamp = strtotime($startTime);
        $endTimestamp = $startTimestamp + ($duration * 60);

        return date('H:i', $endTimestamp);
    }

    /**
     * Convert date to ISO format for database queries.
     */
    public function toIsoDate(string $date): string
    {
        // If already in ISO format, return as is
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        // Convert from European format (DD.MM.YYYY) to ISO (YYYY-MM-DD)
        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $date)) {
            return Carbon::createFromFormat('d.m.Y', $date)->format('Y-m-d');
        }

        // Try to parse with Carbon
        return Carbon::parse($date)->format('Y-m-d');
    }

    /**
     * Check if a staff member is available at a specific date and time.
     */
    public function isStaffAvailable(Staff $staff, string $date, string $time, int $duration, ?string $excludeAppointmentId = null): bool
    {
        return $this->getStaffUnavailabilityReason($staff, $date, $time, $duration, $excludeAppointmentId) === null;
    }

    /**
     * Return a stable reason code when a staff member cannot accept a slot.
     */
    public function getStaffUnavailabilityReason(Staff $staff, string $date, string $time, int $duration, ?string $excludeAppointmentId = null): ?string
    {
        // Convert date to ISO format for database queries
        $isoDate = $this->toIsoDate($date);

        // Convert date to day of week
        $dayOfWeek = strtolower(Carbon::parse($isoDate)->format('l'));

        // Check working hours
        $workingHours = $staff->working_hours[$dayOfWeek] ?? null;
        if (!$workingHours || !$workingHours['is_working']) {
            return 'STAFF_NOT_WORKING';
        }

        // Check if time is within working hours
        $startTime = strtotime($workingHours['start']);
        $endTime = strtotime($workingHours['end']);
        $appointmentTime = strtotime($time);
        $appointmentEndTime = strtotime("+{$duration} minutes", $appointmentTime);

        if ($appointmentTime < $startTime || $appointmentEndTime > $endTime) {
            return 'OUTSIDE_WORKING_HOURS';
        }

        // Check for salon breaks
        $salon = $staff->salon;
        foreach ($salon->salonBreaks as $break) {
            if (!$break->is_active) continue;

            if ($break->appliesTo($isoDate)) {
                $breakStart = strtotime($break->start_time);
                $breakEnd = strtotime($break->end_time);

                // Check if appointment overlaps with break
                if (($appointmentTime < $breakEnd) && ($appointmentEndTime > $breakStart)) {
                    return 'SALON_BREAK';
                }
            }
        }

        // Check for salon vacations
        foreach ($salon->salonVacations as $vacation) {
            if (!$vacation->is_active) continue;

            if ($vacation->isActiveFor($isoDate)) {
                return 'SALON_VACATION';
            }
        }

        // Check for staff breaks
        foreach ($staff->breaks as $break) {
            if (!$break->is_active) continue;

            if ($break->appliesTo($isoDate)) {
                $breakStart = strtotime($break->start_time);
                $breakEnd = strtotime($break->end_time);

                // Check if appointment overlaps with break
                if (($appointmentTime < $breakEnd) && ($appointmentEndTime > $breakStart)) {
                    return 'STAFF_BREAK';
                }
            }
        }

        // Check for staff vacations
        foreach ($staff->vacations as $vacation) {
            if (!$vacation->is_active) continue;

            if ($vacation->isActiveFor($isoDate)) {
                return 'STAFF_VACATION';
            }
        }

        // Check for existing appointments
        // CRITICAL: Use Carbon to ensure proper date comparison
        $carbonDate = Carbon::parse($isoDate);

        $query = Appointment::where('staff_id', $staff->id)
            ->whereDate('date', $carbonDate->format('Y-m-d'))
            ->whereIn('status', Appointment::BLOCKING_STATUSES);

        // Exclude the current appointment if updating
        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        $existingAppointments = $query->get();

        foreach ($existingAppointments as $appointment) {
            $interval = $this->resolveAppointmentInterval($appointment);
            if ($interval === null) {
                continue;
            }

            [$existingStart, $existingEnd] = $interval;

            if ($appointmentTime < $existingEnd && $appointmentEndTime > $existingStart) {
                \Log::warning('AppointmentService: Time slot occupied', [
                    'staff_id' => $staff->id,
                    'requested_time' => $time,
                    'requested_end' => date('H:i', $appointmentEndTime),
                    'existing_id' => $appointment->id,
                    'existing_time' => substr((string) $appointment->time, 0, 5),
                    'existing_end' => substr((string) $appointment->end_time, 0, 5),
                ]);

                return 'TIME_SLOT_TAKEN';
            }
        }

        return null;
    }

    /**
     * Resolve an appointment's blocking interval in unix timestamps.
     *
     * @return array{0: int, 1: int}|null
     */
    public function resolveBlockingInterval(Appointment $appointment): ?array
    {
        return $this->resolveAppointmentInterval($appointment);
    }

    /**
     * Resolve an appointment's blocking interval in unix timestamps.
     *
     * @return array{0: int, 1: int}|null
     */
    private function resolveAppointmentInterval(Appointment $appointment): ?array
    {
        $time = substr(trim((string) $appointment->time), 0, 5);
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            return null;
        }

        $start = strtotime($time);
        $endTime = substr(trim((string) $appointment->end_time), 0, 5);

        if (preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            return [$start, strtotime($endTime)];
        }

        $duration = $this->resolveAppointmentDurationMinutes($appointment);
        if ($duration <= 0) {
            return null;
        }

        return [$start, strtotime("+{$duration} minutes", $start)];
    }

    private function resolveAppointmentDurationMinutes(Appointment $appointment): int
    {
        if (!empty($appointment->service_ids) && is_array($appointment->service_ids)) {
            return (int) Service::query()
                ->whereIn('id', $appointment->service_ids)
                ->sum('duration');
        }

        if ($appointment->service_id) {
            $appointment->loadMissing('service');

            return (int) ($appointment->service?->duration ?? 0);
        }

        return 0;
    }

    /**
     * Get available dates for a staff member and service.
     */
    public function getAvailableDates(Staff $staff, int $serviceId, int $daysAhead = 90): array
    {
        $service = $staff->services()->findOrFail($serviceId);
        $availableDates = [];
        // Enumerate days from "today" in the business timezone so the first
        // bookable day is correct near the UTC/local date boundary.
        $today = now(config('app.business_timezone', 'Europe/Sarajevo'));

        for ($i = 0; $i < $daysAhead; $i++) {
            $date = $today->copy()->addDays($i);
            $isoDateString = $date->format('Y-m-d');
            $europeanDateString = $date->format('d.m.Y');

            // Check if there's at least one available slot on this date
            $dayOfWeek = strtolower($date->format('l'));

            // Check working hours
            $workingHours = $staff->working_hours[$dayOfWeek] ?? null;
            if (!$workingHours || !$workingHours['is_working']) {
                continue;
            }

            // Check for salon vacations
            $salon = $staff->salon;
            $salonVacation = $salon->salonVacations()
                ->whereRaw('is_active = true')
                ->where(function ($query) use ($isoDateString) {
                    $query->whereDate('start_date', '<=', $isoDateString)
                          ->whereDate('end_date', '>=', $isoDateString);
                })->exists();

            if ($salonVacation) {
                continue;
            }

            // Check for staff vacations
            $staffVacation = $staff->vacations()
                ->whereRaw('is_active = true')
                ->where(function ($query) use ($isoDateString) {
                    $query->whereDate('start_date', '<=', $isoDateString)
                          ->whereDate('end_date', '>=', $isoDateString);
                })->exists();

            if ($staffVacation) {
                continue;
            }

            // Generate time slots and check if any are available
            $startTime = $workingHours['start'];
            $endTime = $workingHours['end'];
            $interval = $staff->salon->booking_slot_interval ?? 30;
            $slots = $this->generateTimeSlots($startTime, $endTime, $interval);

            foreach ($slots as $slot) {
                if ($this->isStaffAvailable($staff, $isoDateString, $slot, $service->duration)) {
                    $availableDates[] = $europeanDateString;
                    break;
                }
            }
        }

        return $availableDates;
    }    /**
     * Generate time slots between start and end time.
     */
    private function generateTimeSlots(string $startTime, string $endTime, int $interval = 30): array
    {
        $slots = [];
        $start = strtotime($startTime);
        $end = strtotime($endTime);

        for ($time = $start; $time < $end; $time += $interval * 60) {
            $slots[] = date('H:i', $time);
        }

        return $slots;
    }

    /**
     * Check if a salon has at least one available staff member for a given date and time.
     *
     * @param int $salonId The salon ID
     * @param string $date Date in DD.MM.YYYY or YYYY-MM-DD format
     * @param string|null $time Time in HH:mm format (optional - if null, checks for any available slot)
     * @param int $duration Service duration in minutes (default 60)
     * @return bool True if salon has availability
     */
    public function isSalonAvailable(int $salonId, string $date, ?string $time = null, int $duration = 60): bool
    {
        // Convert date to ISO format
        $isoDate = $this->toIsoDate($date);
        $dayOfWeek = strtolower(Carbon::parse($isoDate)->format('l'));

        // Get all active staff from this salon
        $staffMembers = Staff::where('salon_id', $salonId)
            ->whereRaw('is_active = true')
            ->with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations'])
            ->get();

        if ($staffMembers->isEmpty()) {
            return false;
        }

        foreach ($staffMembers as $staff) {
            // Check working hours for this day
            $workingHours = $staff->working_hours[$dayOfWeek] ?? null;
            if (!$workingHours || !$workingHours['is_working']) {
                continue;
            }

            // Check for salon vacations
            $salonVacation = $staff->salon->salonVacations()
                ->whereRaw('is_active = true')
                ->where(function ($query) use ($isoDate) {
                    $query->whereDate('start_date', '<=', $isoDate)
                          ->whereDate('end_date', '>=', $isoDate);
                })->exists();

            if ($salonVacation) {
                continue;
            }

            // Check for staff vacations
            $staffVacation = $staff->vacations()
                ->whereRaw('is_active = true')
                ->where(function ($query) use ($isoDate) {
                    $query->whereDate('start_date', '<=', $isoDate)
                          ->whereDate('end_date', '>=', $isoDate);
                })->exists();

            if ($staffVacation) {
                continue;
            }

            // If specific time is requested
            if ($time) {
                if ($this->isStaffAvailable($staff, $isoDate, $time, $duration)) {
                    return true;
                }
            } else {
                // Check if any slot is available
                $slots = $this->getAvailableSlots($staff, $isoDate, $duration);
                if (!empty($slots)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get list of salon IDs that have availability on a given date and time.
     *
     * @param string $date Date in DD.MM.YYYY or YYYY-MM-DD format
     * @param string|null $time Time in HH:mm format (optional)
     * @param int $duration Service duration in minutes
     * @return array Array of available salon IDs
     */
    public function getAvailableSalonIds(string $date, ?string $time = null, int $duration = 60): array
    {
        $isoDate = $this->toIsoDate($date);
        $dayOfWeek = strtolower(Carbon::parse($isoDate)->format('l'));

        // Get all salons that have at least one staff working on this day
        $potentialSalonIds = Staff::whereRaw('is_active = true')
            ->whereNotNull("working_hours->{$dayOfWeek}")
            ->whereJsonContains("working_hours->{$dayOfWeek}->is_working", true)
            ->distinct()
            ->pluck('salon_id')
            ->toArray();

        $availableSalonIds = [];

        foreach ($potentialSalonIds as $salonId) {
            if ($this->isSalonAvailable($salonId, $isoDate, $time, $duration)) {
                $availableSalonIds[] = $salonId;
            }
        }

        return $availableSalonIds;
    }

    /**
     * Get available time slots for a staff member on a specific date.
     *
     * @param Staff $staff The staff member
     * @param string $date Date in DD.MM.YYYY or YYYY-MM-DD format
     * @param int $duration Service duration in minutes
     * @return array Available time slots
     */
    public function getAvailableSlots(Staff $staff, string $date, int $duration): array
    {
        // Convert date to ISO format for internal processing
        $isoDate = $this->toIsoDate($date);

        // Get day of week
        $dayOfWeek = strtolower(Carbon::parse($isoDate)->format('l'));

        // Check working hours
        $workingHours = $staff->working_hours[$dayOfWeek] ?? null;
        if (!$workingHours || !$workingHours['is_working']) {
            return [];
        }

        // Check for salon vacations
        $salon = $staff->salon;
        $salonVacation = $salon->salonVacations()
            ->whereRaw('is_active = true')
            ->where(function ($query) use ($isoDate) {
                $query->whereDate('start_date', '<=', $isoDate)
                      ->whereDate('end_date', '>=', $isoDate);
            })->exists();

        if ($salonVacation) {
            return [];
        }

        // Check for staff vacations
        $staffVacation = $staff->vacations()
            ->whereRaw('is_active = true')
            ->where(function ($query) use ($isoDate) {
                $query->whereDate('start_date', '<=', $isoDate)
                      ->whereDate('end_date', '>=', $isoDate);
            })->exists();

        if ($staffVacation) {
            return [];
        }

        // Generate all possible time slots using salon's booking interval
        $startTime = $workingHours['start'];
        $endTime = $workingHours['end'];
        $interval = $staff->salon->booking_slot_interval ?? 30;
        $allSlots = $this->generateTimeSlots($startTime, $endTime, $interval);

        // Filter slots that are actually available
        $availableSlots = [];

        foreach ($allSlots as $slot) {
            // Check if service can finish before end of working hours
            $slotEndTime = strtotime("+{$duration} minutes", strtotime($slot));
            if ($slotEndTime > strtotime($endTime)) {
                continue;
            }

            // Check if slot is available
            if ($this->isStaffAvailable($staff, $isoDate, $slot, $duration)) {
                // If it's today, filter out past times. Slot strings are naive
                // local wall-clock, so compare against business-timezone "now".
                $businessNow = Carbon::now(config('app.business_timezone', 'Europe/Sarajevo'));
                if ($isoDate === $businessNow->format('Y-m-d')) {
                    if ($slot <= $businessNow->format('H:i')) {
                        continue;
                    }
                }
                $availableSlots[] = $slot;
            }
        }

        return $availableSlots;
    }
}
