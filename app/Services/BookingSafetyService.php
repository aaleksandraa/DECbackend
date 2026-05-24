<?php

namespace App\Services;

use App\Models\Appointment;

class BookingSafetyService
{
    public function __construct(
        private AppointmentService $appointmentService,
    ) {}

    public function audit(bool $fix = false): array
    {
        $summary = [
            'fix_applied' => $fix,
            'backfilled_service_ids' => 0,
            'backfilled_booking_source' => 0,
            'backfilled_end_time' => 0,
            'missing_end_time' => 0,
            'overlap_conflicts' => [],
        ];

        Appointment::with('service')
            ->orderBy('id')
            ->chunkById(200, function ($appointments) use ($fix, &$summary) {
                foreach ($appointments as $appointment) {
                    $updates = [];

                    if ($this->isEmptyServiceIds($appointment) && $appointment->service_id) {
                        $updates['service_ids'] = [(int) $appointment->service_id];
                    }

                    if ($this->isBlank($appointment->booking_source ?? null)) {
                        $updates['booking_source'] = $this->isBlank($appointment->source ?? null)
                            ? 'legacy'
                            : (string) $appointment->source;
                    }

                    if ($this->isBlank($appointment->end_time ?? null)) {
                        $duration = (int) ($appointment->service?->duration ?? 0);
                        $time = substr((string) $appointment->time, 0, 5);

                        if ($duration > 0 && preg_match('/^\d{2}:\d{2}$/', $time)) {
                            $updates['end_time'] = $this->appointmentService->calculateEndTime($time, $duration);
                        } else {
                            $summary['missing_end_time']++;
                        }
                    }

                    if (!$fix || empty($updates)) {
                        continue;
                    }

                    $appointment->forceFill($updates)->saveQuietly();

                    if (array_key_exists('service_ids', $updates)) {
                        $summary['backfilled_service_ids']++;
                    }
                    if (array_key_exists('booking_source', $updates)) {
                        $summary['backfilled_booking_source']++;
                    }
                    if (array_key_exists('end_time', $updates)) {
                        $summary['backfilled_end_time']++;
                    }
                }
            });

        $summary['overlap_conflicts'] = $this->findOverlapConflicts();
        $summary['overlap_conflict_count'] = count($summary['overlap_conflicts']);

        return $summary;
    }

    public function findOverlapConflicts(): array
    {
        $appointments = Appointment::query()
            ->whereNull('deleted_at')
            ->whereIn('status', Appointment::BLOCKING_STATUSES)
            ->whereNotNull('staff_id')
            ->whereNotNull('date')
            ->whereNotNull('time')
            ->orderBy('staff_id')
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        $conflicts = [];
        $groups = $appointments->groupBy(fn ($appointment) => $appointment->staff_id.'|'.$appointment->date->format('Y-m-d'));

        foreach ($groups as $group) {
            $items = $group->values();
            for ($i = 0; $i < $items->count(); $i++) {
                for ($j = $i + 1; $j < $items->count(); $j++) {
                    $first = $items[$i];
                    $second = $items[$j];

                    if ($this->isBlank($first->end_time) || $this->isBlank($second->end_time)) {
                        continue;
                    }

                    $firstStart = strtotime(substr((string) $first->time, 0, 5));
                    $firstEnd = strtotime(substr((string) $first->end_time, 0, 5));
                    $secondStart = strtotime(substr((string) $second->time, 0, 5));
                    $secondEnd = strtotime(substr((string) $second->end_time, 0, 5));

                    if ($firstStart < $secondEnd && $firstEnd > $secondStart) {
                        $conflicts[] = [
                            'staff_id' => $first->staff_id,
                            'date' => $first->date->format('Y-m-d'),
                            'first_id' => $first->id,
                            'first_time' => substr((string) $first->time, 0, 5),
                            'first_end_time' => substr((string) $first->end_time, 0, 5),
                            'second_id' => $second->id,
                            'second_time' => substr((string) $second->time, 0, 5),
                            'second_end_time' => substr((string) $second->end_time, 0, 5),
                        ];
                    }
                }
            }
        }

        return $conflicts;
    }

    private function isEmptyServiceIds(Appointment $appointment): bool
    {
        return empty($appointment->service_ids) || !is_array($appointment->service_ids);
    }

    private function isBlank(mixed $value): bool
    {
        return trim((string) $value) === '';
    }
}
