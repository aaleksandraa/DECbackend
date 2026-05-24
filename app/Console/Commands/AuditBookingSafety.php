<?php

namespace App\Console\Commands;

use App\Services\BookingSafetyService;
use Illuminate\Console\Command;

class AuditBookingSafety extends Command
{
    protected $signature = 'appointments:audit-booking-safety
        {--fix : Backfill empty legacy-safe appointment fields}
        {--json : Output machine-readable JSON}';

    protected $description = 'Audit appointment overlap conflicts and optionally backfill empty legacy-safe fields.';

    public function handle(BookingSafetyService $bookingSafetyService): int
    {
        $fix = (bool) $this->option('fix');
        $summary = $bookingSafetyService->audit($fix);

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info($fix ? 'Backfill completed.' : 'Audit completed. No data was changed.');
        $this->line('service_ids backfilled: '.$summary['backfilled_service_ids']);
        $this->line('booking_source backfilled: '.$summary['backfilled_booking_source']);
        $this->line('end_time backfilled: '.$summary['backfilled_end_time']);
        $this->line('appointments still missing auditable end_time: '.$summary['missing_end_time']);
        $this->line('active overlap conflicts found: '.count($summary['overlap_conflicts']));

        foreach (array_slice($summary['overlap_conflicts'], 0, 25) as $conflict) {
            $this->warn(sprintf(
                'staff=%s date=%s appointments=%s(%s-%s) and %s(%s-%s)',
                $conflict['staff_id'],
                $conflict['date'],
                $conflict['first_id'],
                $conflict['first_time'],
                $conflict['first_end_time'],
                $conflict['second_id'],
                $conflict['second_time'],
                $conflict['second_end_time']
            ));
        }

        if (count($summary['overlap_conflicts']) > 25) {
            $this->warn('Only first 25 conflicts shown. Re-run with --json for the full list.');
        }

        return self::SUCCESS;
    }
}
