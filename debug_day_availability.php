<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== DEBUG: Day Availability for 27.12.2025 (Saturday) ===\n\n";

$date = '2025-12-27'; // PostgreSQL format

// Get all appointments for this date
$appointments = DB::table('appointments')
    ->where('date', $date)
    ->orderBy('time')
    ->get();

echo "Date: 27.12.2025 (stored as: $date)\n";
echo "Total appointments: " . $appointments->count() . "\n\n";

if ($appointments->count() > 0) {
    echo "Appointments:\n";
    foreach ($appointments as $app) {
        $startParts = explode(':', $app->time);
        $endParts = explode(':', $app->end_time);
        $startMinutes = (int)$startParts[0] * 60 + (int)$startParts[1];
        $endMinutes = (int)$endParts[0] * 60 + (int)$endParts[1];
        $duration = $endMinutes - $startMinutes;

        echo "  - {$app->time} - {$app->end_time} ({$duration} min) - {$app->client_name}\n";
    }

    // Calculate total booked minutes
    $totalBookedMinutes = 0;
    foreach ($appointments as $app) {
        $startParts = explode(':', $app->time);
        $endParts = explode(':', $app->end_time);
        $startMinutes = (int)$startParts[0] * 60 + (int)$startParts[1];
        $endMinutes = (int)$endParts[0] * 60 + (int)$endParts[1];
        $totalBookedMinutes += ($endMinutes - $startMinutes);
    }

    echo "\nTotal booked minutes: $totalBookedMinutes\n";

    // Get salon working hours for Saturday (27.12.2025 is Saturday)
    $salon = DB::table('salons')->first();
    if ($salon && $salon->working_hours) {
        $workingHours = json_decode($salon->working_hours, true);
        $saturday = $workingHours['saturday'] ?? null;

        if ($saturday && isset($saturday['is_open']) && $saturday['is_open']) {
            $openTime = $saturday['open'];
            $closeTime = $saturday['close'];

            $openParts = explode(':', $openTime);
            $closeParts = explode(':', $closeTime);
            $openMinutes = (int)$openParts[0] * 60 + (int)$openParts[1];
            $closeMinutes = (int)$closeParts[0] * 60 + (int)$closeParts[1];
            $totalWorkingMinutes = $closeMinutes - $openMinutes;

            echo "Working hours: $openTime - $closeTime\n";
            echo "Total working minutes: $totalWorkingMinutes\n";

            $percentage = ($totalBookedMinutes / $totalWorkingMinutes) * 100;
            echo "Booked percentage: " . round($percentage, 2) . "%\n";

            if ($percentage >= 80) {
                echo "Status: FULL (red)\n";
            } else {
                echo "Status: PARTIAL (green)\n";
            }

            // Show free slots
            echo "\nFree slots:\n";
            $currentMinutes = $openMinutes;
            $sortedAppointments = $appointments->sortBy('time');

            foreach ($sortedAppointments as $app) {
                $startParts = explode(':', $app->time);
                $appStartMinutes = (int)$startParts[0] * 60 + (int)$startParts[1];

                if ($currentMinutes < $appStartMinutes) {
                    $freeStartHour = floor($currentMinutes / 60);
                    $freeStartMinute = $currentMinutes % 60;
                    $freeEndHour = floor($appStartMinutes / 60);
                    $freeEndMinute = $appStartMinutes % 60;
                    $freeDuration = $appStartMinutes - $currentMinutes;

                    echo "  - " . sprintf('%02d:%02d', $freeStartHour, $freeStartMinute) .
                         " - " . sprintf('%02d:%02d', $freeEndHour, $freeEndMinute) .
                         " ($freeDuration min)\n";
                }

                $endParts = explode(':', $app->end_time);
                $currentMinutes = (int)$endParts[0] * 60 + (int)$endParts[1];
            }

            // Final free slot
            if ($currentMinutes < $closeMinutes) {
                $freeStartHour = floor($currentMinutes / 60);
                $freeStartMinute = $currentMinutes % 60;
                $freeEndHour = floor($closeMinutes / 60);
                $freeEndMinute = $closeMinutes % 60;
                $freeDuration = $closeMinutes - $currentMinutes;

                echo "  - " . sprintf('%02d:%02d', $freeStartHour, $freeStartMinute) .
                     " - " . sprintf('%02d:%02d', $freeEndHour, $freeEndMinute) .
                     " ($freeDuration min)\n";
            }
        } else {
            echo "Salon is closed on Saturday\n";
        }
    }
}

echo "\n=== END DEBUG ===\n";
