<?php

namespace App\Observers;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AppointmentObserver
{
    /**
     * Handle the Appointment "creating" event.
     * Automatically calculate end_time based on service duration.
     */
    public function creating(Appointment $appointment): void
    {
        $this->calculateEndTime($appointment);
    }

    /**
     * Handle the Appointment "updating" event.
     * Recalculate end_time if time or service changes.
     */
    public function updating(Appointment $appointment): void
    {
        // Only recalculate if time or service_id changed
        if ($appointment->isDirty(['time', 'service_id', 'date'])) {
            $this->calculateEndTime($appointment);
        }
    }

    /**
     * Handle the Appointment "saved" event.
     * Clear availability cache when appointment is created or updated.
     */
    public function saved(Appointment $appointment): void
    {
        $this->clearAvailabilityCache($appointment);
    }

    /**
     * Handle the Appointment "deleted" event.
     * Clear availability cache when appointment is deleted.
     */
    public function deleted(Appointment $appointment): void
    {
        $this->clearAvailabilityCache($appointment);
    }

    /**
     * Calculate and set end_time based on service duration.
     */
    private function calculateEndTime(Appointment $appointment): void
    {
        // Load service if not already loaded
        if (!$appointment->relationLoaded('service') && $appointment->service_id) {
            $appointment->load('service');
        }

        if ($appointment->service) {
            // Parse date and time
            $dateStr = $appointment->date instanceof Carbon
                ? $appointment->date->format('Y-m-d')
                : $appointment->date;

            $start = Carbon::parse($dateStr . ' ' . $appointment->time);
            $end = $start->copy()->addMinutes($appointment->service->duration);
            $appointment->end_time = $end->format('H:i:s');

            Log::info('AppointmentObserver: Calculated end_time', [
                'appointment_id' => $appointment->id ?? 'new',
                'date' => $dateStr,
                'start' => $appointment->time,
                'duration' => $appointment->service->duration,
                'end_time' => $appointment->end_time
            ]);
        } else {
            Log::warning('AppointmentObserver: Cannot calculate end_time - service not found', [
                'appointment_id' => $appointment->id ?? 'new',
                'service_id' => $appointment->service_id
            ]);
        }
    }

    /**
     * Clear availability cache for the affected staff and date.
     */
    private function clearAvailabilityCache(Appointment $appointment): void
    {
        $dateStr = $appointment->date instanceof Carbon
            ? $appointment->date->format('Y-m-d')
            : $appointment->date;

        // Clear all cache keys for this staff and date
        // Pattern: available_slots:{staff_id}:{date}:{duration}
        $pattern = "available_slots:{$appointment->staff_id}:{$dateStr}:*";

        // Since Laravel doesn't support wildcard cache deletion easily,
        // we'll clear common durations (15, 30, 45, 60, 90, 120 minutes)
        $commonDurations = [15, 30, 45, 60, 90, 120];

        foreach ($commonDurations as $duration) {
            $cacheKey = "available_slots:{$appointment->staff_id}:{$dateStr}:{$duration}";
            Cache::forget($cacheKey);
        }

        Log::info('AppointmentObserver: Cleared availability cache', [
            'staff_id' => $appointment->staff_id,
            'date' => $dateStr,
            'appointment_id' => $appointment->id
        ]);
    }
}
