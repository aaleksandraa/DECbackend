<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CompleteExpiredAppointments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointments:complete-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically mark expired appointments as completed';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $now = Carbon::now();
        $today = $now->format('Y-m-d');
        $currentTime = $now->format('H:i:s');

        // Find all pending/confirmed/in_progress appointments that have passed their end time
        $expiredAppointments = Appointment::whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->where(function ($query) use ($today, $currentTime) {
                // Past dates
                $query->where('date', '<', $today)
                    // Or today but end_time has passed
                    ->orWhere(function ($query) use ($today, $currentTime) {
                        $query->where('date', $today)
                            ->whereRaw(
                                "CAST(COALESCE(NULLIF(end_time, ''), NULLIF(time, ''), '00:00') AS TIME) <= ?",
                                [$currentTime]
                            );
                    });
            })
            ->with(['salon', 'staff', 'service', 'client', 'review'])
            ->get();

        $count = $expiredAppointments->count();

        if ($count === 0) {
            $this->info('No expired appointments found.');
            return self::SUCCESS;
        }

        foreach ($expiredAppointments as $appointment) {
            $oldStatus = $appointment->status;
            $appointment->update(['status' => 'completed']);
            $appointment->refresh();

            $notificationService->sendAppointmentStatusChangeNotifications($appointment, $oldStatus);

            Log::info('Appointment auto-completed', [
                'appointment_id' => $appointment->id,
                'date' => $appointment->date,
                'end_time' => $appointment->end_time,
            ]);
        }

        $this->info("Successfully marked {$count} appointments as completed.");

        return self::SUCCESS;
    }
}
