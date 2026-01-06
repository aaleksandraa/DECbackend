<?php

/**
 * Fix Multi-Service Appointment End Times
 *
 * This script fixes end_time for all appointments based on total service duration.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;

echo "========================================\n";
echo "Fix Multi-Service End Times\n";
echo "========================================\n\n";

$fixed = 0;
$errors = 0;

// Fix multi-service appointments
$multiServiceAppointments = Appointment::whereNotNull('service_ids')
    ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
    ->get();

echo "Processing " . $multiServiceAppointments->count() . " multi-service appointments...\n\n";

foreach ($multiServiceAppointments as $appointment) {
    $serviceIds = $appointment->service_ids;

    if (empty($serviceIds) || !is_array($serviceIds)) {
        continue;
    }

    try {
        // Load services and calculate total duration
        $services = Service::whereIn('id', $serviceIds)->get();
        $totalDuration = 0;

        foreach ($services as $service) {
            $totalDuration += $service->duration;
        }

        // Calculate correct end_time
        $startParts = explode(':', $appointment->time);
        $startMinutes = (int)$startParts[0] * 60 + (int)$startParts[1];
        $endMinutes = $startMinutes + $totalDuration;
        $correctEndTime = sprintf('%02d:%02d', floor($endMinutes / 60), $endMinutes % 60);

        // Update if different
        if ($appointment->end_time !== $correctEndTime) {
            $oldEndTime = $appointment->end_time;
            $appointment->end_time = $correctEndTime;
            $appointment->save();

            echo "✅ Fixed Appointment #{$appointment->id}\n";
            echo "   Date: {$appointment->date->format('d.m.Y')} {$appointment->time}\n";
            echo "   Duration: {$totalDuration} min\n";
            echo "   Old end_time: {$oldEndTime}\n";
            echo "   New end_time: {$correctEndTime}\n\n";

            $fixed++;
        }
    } catch (\Exception $e) {
        echo "❌ Error fixing Appointment #{$appointment->id}: {$e->getMessage()}\n\n";
        $errors++;
    }
}

// Fix single-service appointments
$singleServiceAppointments = Appointment::whereNotNull('service_id')
    ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
    ->with('service')
    ->get();

echo "Processing " . $singleServiceAppointments->count() . " single-service appointments...\n\n";

foreach ($singleServiceAppointments as $appointment) {
    if (!$appointment->service) {
        continue;
    }

    try {
        $duration = $appointment->service->duration;

        // Calculate correct end_time
        $startParts = explode(':', $appointment->time);
        $startMinutes = (int)$startParts[0] * 60 + (int)$startParts[1];
        $endMinutes = $startMinutes + $duration;
        $correctEndTime = sprintf('%02d:%02d', floor($endMinutes / 60), $endMinutes % 60);

        // Update if different
        if ($appointment->end_time !== $correctEndTime) {
            $oldEndTime = $appointment->end_time;
            $appointment->end_time = $correctEndTime;
            $appointment->save();

            $fixed++;
        }
    } catch (\Exception $e) {
        $errors++;
    }
}

echo "========================================\n";
echo "Summary\n";
echo "========================================\n\n";
echo "✅ Fixed: {$fixed} appointments\n";
echo "❌ Errors: {$errors}\n\n";

if ($fixed > 0) {
    echo "All end_time values have been corrected!\n";
    echo "Availability checks should now work correctly.\n\n";
}
