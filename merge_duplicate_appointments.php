<?php

/**
 * Merge Duplicate Multi-Service Appointments
 *
 * This script finds appointments that were created as separate appointments
 * (old way) and merges them into single appointments with service_ids (new way).
 *
 * Criteria for merging:
 * - Same client, staff, date
 * - Consecutive time slots
 * - Created within 1 minute of each other
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "========================================\n";
echo "Merge Duplicate Multi-Service Appointments\n";
echo "========================================\n\n";

// Find potential duplicates
// Group by client, staff, date and look for appointments created within 1 minute
$potentialDuplicates = DB::select("
    SELECT
        a1.id as id1,
        a2.id as id2,
        a1.client_id,
        a1.client_name,
        a1.staff_id,
        a1.date,
        a1.time as time1,
        a2.time as time2,
        a1.service_id as service1,
        a2.service_id as service2,
        a1.created_at as created1,
        a2.created_at as created2
    FROM appointments a1
    JOIN appointments a2 ON
        a1.staff_id = a2.staff_id AND
        a1.date = a2.date AND
        a1.id < a2.id AND
        a1.deleted_at IS NULL AND
        a2.deleted_at IS NULL AND
        (a1.client_id = a2.client_id OR (a1.client_id IS NULL AND a2.client_id IS NULL AND a1.client_name = a2.client_name))
    WHERE
        a1.service_ids IS NULL AND
        a2.service_ids IS NULL AND
        a1.status IN ('pending', 'confirmed', 'in_progress') AND
        a2.status IN ('pending', 'confirmed', 'in_progress') AND
        ABS(EXTRACT(EPOCH FROM (a2.created_at - a1.created_at))) < 60
    ORDER BY a1.date, a1.time
");

echo "Found " . count($potentialDuplicates) . " potential duplicate pairs\n\n";

if (empty($potentialDuplicates)) {
    echo "✅ No duplicates found! All appointments are already merged.\n\n";
    exit(0);
}

$merged = 0;
$skipped = 0;
$errors = 0;

foreach ($potentialDuplicates as $duplicate) {
    try {
        // Load both appointments
        $apt1 = Appointment::find($duplicate->id1);
        $apt2 = Appointment::find($duplicate->id2);

        if (!$apt1 || !$apt2) {
            echo "⚠️  Skipping: One or both appointments not found\n";
            $skipped++;
            continue;
        }

        // Load services
        $service1 = Service::find($apt1->service_id);
        $service2 = Service::find($apt2->service_id);

        if (!$service1 || !$service2) {
            echo "⚠️  Skipping: One or both services not found\n";
            $skipped++;
            continue;
        }

        // Check if times are consecutive
        $time1Parts = explode(':', $apt1->time);
        $time1Minutes = (int)$time1Parts[0] * 60 + (int)$time1Parts[1];

        $time2Parts = explode(':', $apt2->time);
        $time2Minutes = (int)$time2Parts[0] * 60 + (int)$time2Parts[1];

        $expectedTime2 = $time1Minutes + $service1->duration;

        // Allow 5 minute tolerance
        if (abs($time2Minutes - $expectedTime2) > 5) {
            echo "⚠️  Skipping: Times not consecutive (#{$apt1->id} and #{$apt2->id})\n";
            echo "   Time 1: {$apt1->time}, Duration: {$service1->duration}min, Expected: " . sprintf('%02d:%02d', floor($expectedTime2 / 60), $expectedTime2 % 60) . ", Actual: {$apt2->time}\n";
            $skipped++;
            continue;
        }

        echo "🔄 Merging appointments #{$apt1->id} and #{$apt2->id}\n";
        echo "   Client: {$apt1->client_name}\n";
        echo "   Date: {$apt1->date->format('d.m.Y')}\n";
        echo "   Services: {$service1->name} + {$service2->name}\n";
        echo "   Time: {$apt1->time} - {$apt2->end_time}\n";

        DB::transaction(function () use ($apt1, $apt2, $service1, $service2) {
            // Update first appointment with both services
            $apt1->service_ids = [$apt1->service_id, $apt2->service_id];
            $apt1->end_time = $apt2->end_time;
            $apt1->total_price = ($service1->discount_price ?? $service1->price) + ($service2->discount_price ?? $service2->price);

            // Merge notes if second appointment has notes
            if ($apt2->notes && $apt2->notes !== $apt1->notes) {
                $apt1->notes = trim(($apt1->notes ?? '') . "\n" . $apt2->notes);
            }

            $apt1->save();

            // Soft delete second appointment
            $apt2->delete();
        });

        echo "   ✅ Merged successfully!\n\n";
        $merged++;

    } catch (\Exception $e) {
        echo "   ❌ Error: {$e->getMessage()}\n\n";
        $errors++;
    }
}

echo "========================================\n";
echo "Summary\n";
echo "========================================\n\n";
echo "✅ Merged: {$merged} appointment pairs\n";
echo "⚠️  Skipped: {$skipped} pairs\n";
echo "❌ Errors: {$errors}\n\n";

if ($merged > 0) {
    echo "Successfully merged {$merged} duplicate appointments!\n";
    echo "These appointments now show as single appointments with multiple services.\n\n";
}

if ($skipped > 0) {
    echo "Note: {$skipped} pairs were skipped because:\n";
    echo "  - Times were not consecutive\n";
    echo "  - Services or appointments were not found\n";
    echo "  - Other validation issues\n\n";
}

echo "Run this script again to check for any remaining duplicates.\n\n";
