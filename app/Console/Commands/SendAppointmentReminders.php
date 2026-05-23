<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminder;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendAppointmentReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'appointments:send-reminders
                            {--type=day_before : Reminder type (day_before|same_day)}
                            {--dry-run : Preview reminders without dispatching jobs}';

    /**
     * The console command description.
     */
    protected $description = 'Send reminder notifications for appointments (day-before and same-day)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timezone = 'Europe/Sarajevo';
        $now = Carbon::now($timezone);
        $type = strtolower((string) $this->option('type'));
        $dryRun = (bool) $this->option('dry-run');

        if (!in_array($type, ['day_before', 'same_day'], true)) {
            $this->error("Invalid --type value '{$type}'. Allowed values: day_before, same_day.");

            return Command::FAILURE;
        }

        $targetDate = $type === 'day_before'
            ? $now->copy()->addDay()->toDateString()
            : $now->toDateString();

        // Include both registered users and guest bookings with email.
        $appointmentsQuery = Appointment::query()
            ->whereDate('date', $targetDate)
            ->where('status', 'confirmed')
            ->where(function ($query) {
                // Registered users with client_id
                $query->whereNotNull('client_id')
                    // OR guest bookings with email
                    ->orWhereNotNull('client_email');
            });

        // Same-day reminders only for appointments that have not started yet.
        if ($type === 'same_day') {
            $appointmentsQuery->whereRaw(
                "CAST(COALESCE(NULLIF(time, ''), '00:00') AS TIME) >= ?",
                [$now->format('H:i:s')]
            );
        }

        $appointments = $appointmentsQuery
            ->with(['client', 'salon', 'service', 'staff'])
            ->orderBy('time')
            ->get();

        $dispatched = 0;
        $skippedNoEmail = 0;
        $skippedDuplicate = 0;
        $lockTtlSeconds = $this->lockTtlSeconds($now);

        foreach ($appointments as $appointment) {
            // Check if we have an email address (registered user or guest)
            $hasEmail = ($appointment->client && $appointment->client->email)
                        || !empty($appointment->client_email);

            if (!$hasEmail) {
                $skippedNoEmail++;
                $this->warn("Skipped appointment {$appointment->id} - no email address");
                continue;
            }

            $lockKey = $this->buildLockKey((int) $appointment->id, $type, $targetDate);
            if (!Cache::add($lockKey, $now->timestamp, $lockTtlSeconds)) {
                $skippedDuplicate++;
                continue;
            }

            if (!$dryRun) {
                SendAppointmentReminder::dispatch($appointment, $type);
            }

            $dispatched++;
        }

        $runTypeLabel = $type === 'day_before' ? 'day-before' : 'same-day';
        $verb = $dryRun ? 'would dispatch' : 'dispatched';
        $this->info("Reminder run ({$runTypeLabel}) for {$targetDate}: {$verb} {$dispatched} appointment(s).");

        if ($skippedNoEmail > 0) {
            $this->info("Skipped {$skippedNoEmail} appointment(s) with no email.");
        }
        if ($skippedDuplicate > 0) {
            $this->info("Skipped {$skippedDuplicate} appointment(s) already queued in this cycle.");
        }

        return Command::SUCCESS;
    }

    private function buildLockKey(int $appointmentId, string $type, string $targetDate): string
    {
        return "appointment_reminder:{$type}:{$targetDate}:{$appointmentId}";
    }

    private function lockTtlSeconds(Carbon $now): int
    {
        // Keep idempotency lock alive for the remainder of the day.
        return max(3600, $now->copy()->endOfDay()->addMinutes(5)->diffInSeconds($now));
    }
}
