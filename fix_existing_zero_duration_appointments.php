<?php

/**
 * Fix existing appointments that have ONLY zero duration services
 * These appointments block slots but don't reserve any time
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n";
echo "========================================\n";
echo "Fix Existing Zero Duration Appointments\n";
echo "========================================\n\n";

// Find appointments where time = end_time (zero duration)
echo "Finding appointments with zero duration...\n";
echo "----------------------------\n";

$zeroAppointments = DB::select("
    SELECT
        a.id,
        a.date,
        a.time,
        a.end_time,
        a.status,
        a.client_name,
        s.name as service_name,
        s.duration,
        st.name as staff_name
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN staff st ON a.staff_id = st.id
    WHERE a.time = a.end_time
    AND a.status IN ('pending', 'confirmed', 'in_progress')
    ORDER BY a.date DESC, a.time DESC
");

if (empty($zeroAppointments)) {
    echo "✅ No appointments with zero duration found\n";
    exit;
}

echo "Found " . count($zeroAppointments) . " appointments:\n\n";
foreach ($zeroAppointments as $apt) {
    echo "ID {$apt->id}: {$apt->date} {$apt->time} | {$apt->client_name} | {$apt->service_name} | Staff: {$apt->staff_name}\n";
}

echo "\n";
echo "These appointments block slots but don't reserve any time.\n";
echo "They should be cancelled or completed.\n";
echo "\n";

// Ask what to do
echo "What do you want to do?\n";
echo "1. Cancel them (status = 'cancelled')\n";
echo "2. Mark as completed (status = 'completed')\n";
echo "3. Delete them\n";
echo "4. Exit without changes\n";
echo "\n";
echo "Enter choice (1-4): ";

$handle = fopen("php://stdin", "r");
$choice = trim(fgets($handle));
fclose($handle);

if ($choice == '4') {
    echo "Exiting without changes.\n";
    exit;
}

$action = '';
$newStatus = '';

switch ($choice) {
    case '1':
        $action = 'cancel';
        $newStatus = 'cancelled';
        break;
    case '2':
        $action = 'complete';
        $newStatus = 'completed';
        break;
    case '3':
        $action = 'delete';
        break;
    default:
        echo "Invalid choice. Exiting.\n";
        exit;
}

echo "\n";
echo "Are you sure you want to {$action} " . count($zeroAppointments) . " appointments? (yes/no): ";
$handle = fopen("php://stdin", "r");
$confirm = trim(fgets($handle));
fclose($handle);

if ($confirm != 'yes') {
    echo "Aborted.\n";
    exit;
}

echo "\n";
echo "Processing...\n";
echo "----------------------------\n";

$count = 0;
foreach ($zeroAppointments as $apt) {
    if ($action == 'delete') {
        DB::delete("DELETE FROM appointments WHERE id = ?", [$apt->id]);
        echo "✅ Deleted appointment ID {$apt->id}\n";
    } else {
        DB::update("UPDATE appointments SET status = ? WHERE id = ?", [$newStatus, $apt->id]);
        echo "✅ Updated appointment ID {$apt->id} to {$newStatus}\n";
    }
    $count++;
}

echo "\n";
echo "========================================\n";
echo "COMPLETE\n";
echo "========================================\n";
echo "\n";
echo "Processed {$count} appointments.\n";
echo "\n";
echo "Next steps:\n";
echo "1. Check that slots are now available\n";
echo "2. Test booking on website and widget\n";
echo "3. Monitor for new zero duration bookings\n";
echo "\n";
