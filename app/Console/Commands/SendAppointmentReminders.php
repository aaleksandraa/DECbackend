<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminder;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'appointments:send-reminders';

    /**
     * The console command description.
     */
    protected $description = 'Send reminder notifications for appointments scheduled for tomorrow';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');

        // Get all confirmed appointments for tomorrow
        // Include both registered users and guest bookings with email
        $appointments = Appointment::where('date', $tomorrow)
            ->where('status', 'confirmed')
            ->where(function ($query) {
                // Registered users with client_id
                $query->whereNotNull('client_id')
                    // OR guest bookings with email
                    ->orWhereNotNull('client_email');
            })
            ->with(['client', 'salon', 'service', 'staff'])
            ->get();

        $count = 0;
        $skipped = 0;

        foreach ($appointments as $appointment) {
            // Check if we have an email address (registered user or guest)
            $hasEmail = ($appointment->client && $appointment->client->email)
                        || !empty($appointment->client_email);

            if ($hasEmail) {
                SendAppointmentReminder::dispatch($appointment);
                $count++;
            } else {
                $skipped++;
                $this->warn("Skipped appointment {$appointment->id} - no email address");
            }
        }

        $this->info("Dispatched {$count} reminder notifications for tomorrow's appointments.");
        if ($skipped > 0) {
            $this->info("Skipped {$skipped} appointments (no email address).");
        }

        return Command::SUCCESS;
    }
}
