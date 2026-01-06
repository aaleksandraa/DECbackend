<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Fixing Zero Duration Appointments ===\n\n";

// Find appointments where end_time = time (zero duration)
$zeroAppointments = \App\Models\Appointment::whereRaw('end_time = time')
    ->with('service')
    ->get();

echo "Found {$zeroAppointments->count()} appointments with zero duration (end_time = time):\n\n";

foreach ($zeroAppointments as $apt) {
    echo "ID: {$apt->id}\n";
    echo "  Service: {$apt->service->name} (duration: {$apt->service->duration} min)\n";
    echo "  Date: {$apt->date}\n";
    echo "  Time: {$apt->time} - {$apt->end_time}\n";
    echo "  Status: {$apt->status}\n";
    echo "  Client: {$apt->client_name}\n";

    // If service has duration, recalculate end_time
    if ($apt->service->duration > 0) {
        $timeParts = explode(':', $apt->time);
        $startMinutes = (int)$timeParts[0] * 60 + (int)$timeParts[1];
        $endMinutes = $startMinutes + $apt->service->duration;
        $correctEndTime = sprintf('%02d:%02d:00', floor($endMinutes / 60), $endMinutes % 60);

        echo "  ✅ Fixing: Setting end_time to {$correctEndTime}\n";
        $apt->update(['end_time' => $correctEndTime]);
    } else {
        // Service has 0 duration - this is an add-on service
        // Check if there are other appointments for same client/staff/date/time with duration
        $mainAppointment = \App\Models\Appointment::where('staff_id', $apt->staff_id)
            ->where('date', $apt->date)
            ->where('time', $apt->time)
            ->where('id', '!=', $apt->id)
            ->whereHas('service', function($q) {
                $q->where('duration', '>', 0);
            })
            ->first();

        if ($mainAppointment) {
            echo "  ✅ Fixing: Setting end_time to match main appointment {$mainAppointment->id} ({$mainAppointment->end_time})\n";
            $apt->update(['end_time' => $mainAppointment->end_time]);
        } else {
            echo "  ⚠️  WARNING: 0-duration service with no main appointment found!\n";
            echo "     This appointment should probably be deleted or have a different time.\n";
            echo "     Keeping as-is for manual review.\n";
        }
    }

    echo "\n";
}

echo "\n=== Summary ===\n";
echo "Fixed {$zeroAppointments->count()} appointments with zero duration.\n";
echo "\nPlease verify the changes in the database.\n";
