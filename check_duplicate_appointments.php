<?php

/**
 * Check for Duplicate Multi-Service Appointments
 *
 * This script finds appointments that might be duplicates
 * (created as separate appointments for multi-service bookings).
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "Check for Duplicate Appointments\n";
echo "========================================\n\n";

// Find potential duplicates
$potentialDuplicates = DB::select("
    SELECT
        a1.id as id1,
        a2.id as id2,
        a1.client_name,
        s1.name as staff_name,
        a1.date,
        a1.time as time1,
        a2.time as time2,
        a1.end_time as end1,
        a2.end_time as end2,
        srv1.name as service1,
        srv2.name as service2,
        srv1.duration as duration1,
        srv2.duration as duration2,
        a1.created_at as created1,
        a2.created_at as created2,
        ABS(EXTRACT(EPOCH FROM (a2.created_at - a1.created_at))) as time_diff
    FROM appointments a1
    JOIN appointments a2 ON
        a1.staff_id = a2.staff_id AND
        a1.date = a2.date AND
        a1.id < a2.id AND
        a1.deleted_at IS NULL AND
        a2.deleted_at IS NULL AND
        (a1.client_id = a2.client_id OR (a1.client_id IS NULL AND a2.client_id IS NULL AND a1.client_name = a2.client_name))
    JOIN staff s1 ON a1.staff_id = s1.id
    JOIN services srv1 ON a1.service_id = srv1.id
    JOIN services srv2 ON a2.service_id = srv2.id
    WHERE
        a1.service_ids IS NULL AND
        a2.service_ids IS NULL AND
        a1.status IN ('pending', 'confirmed', 'in_progress') AND
        a2.status IN ('pending', 'confirmed', 'in_progress') AND
        ABS(EXTRACT(EPOCH FROM (a2.created_at - a1.created_at))) < 60
    ORDER BY a1.date DESC, a1.time
    LIMIT 20
");

if (empty($potentialDuplicates)) {
    echo "✅ No duplicate appointments found!\n\n";
    echo "All appointments are either:\n";
    echo "  - Single service appointments\n";
    echo "  - Already merged multi-service appointments (with service_ids)\n\n";
    exit(0);
}

echo "Found " . count($potentialDuplicates) . " potential duplicate pairs:\n\n";

foreach ($potentialDuplicates as $dup) {
    $date = date('d.m.Y', strtotime($dup->date));
    $timeDiff = round($dup->time_diff);

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Appointment Pair: #{$dup->id1} + #{$dup->id2}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Client: {$dup->client_name}\n";
    echo "Staff: {$dup->staff_name}\n";
    echo "Date: {$date}\n";
    echo "\n";
    echo "Appointment 1:\n";
    echo "  Service: {$dup->service1} ({$dup->duration1} min)\n";
    echo "  Time: {$dup->time1} - {$dup->end1}\n";
    echo "\n";
    echo "Appointment 2:\n";
    echo "  Service: {$dup->service2} ({$dup->duration2} min)\n";
    echo "  Time: {$dup->time2} - {$dup->end2}\n";
    echo "\n";
    echo "Created: {$timeDiff} seconds apart\n";

    // Check if times are consecutive
    $time1Parts = explode(':', $dup->time1);
    $time1Minutes = (int)$time1Parts[0] * 60 + (int)$time1Parts[1];

    $time2Parts = explode(':', $dup->time2);
    $time2Minutes = (int)$time2Parts[0] * 60 + (int)$time2Parts[1];

    $expectedTime2 = $time1Minutes + $dup->duration1;
    $actualDiff = abs($time2Minutes - $expectedTime2);

    if ($actualDiff <= 5) {
        echo "Status: ✅ CONSECUTIVE (can be merged)\n";
    } else {
        echo "Status: ⚠️  NOT CONSECUTIVE (gap: {$actualDiff} min)\n";
    }
    echo "\n";
}

echo "========================================\n";
echo "Summary\n";
echo "========================================\n\n";
echo "Total duplicate pairs found: " . count($potentialDuplicates) . "\n\n";

echo "To merge these duplicates, run:\n";
echo "php merge_duplicate_appointments.php\n\n";

echo "This will:\n";
echo "  1. Merge consecutive appointments into single appointments\n";
echo "  2. Update service_ids to include all services\n";
echo "  3. Update end_time to reflect total duration\n";
echo "  4. Soft delete the duplicate appointments\n\n";
