<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Appointment;
use Carbon\Carbon;

echo "=== Testing Appointment Dates ===\n\n";

// Get all appointments for salon 5
$appointments = Appointment::where('salon_id', 5)
    ->orderBy('date', 'asc')
    ->get(['id', 'date', 'time', 'client_name']);

echo "Total appointments for salon 5: " . $appointments->count() . "\n\n";

// Group by month
$byMonth = [];
foreach ($appointments as $apt) {
    $date = Carbon::parse($apt->date);
    $monthKey = $date->format('Y-m');

    if (!isset($byMonth[$monthKey])) {
        $byMonth[$monthKey] = 0;
    }
    $byMonth[$monthKey]++;
}

echo "Appointments by month:\n";
foreach ($byMonth as $month => $count) {
    echo "  $month: $count appointments\n";
}

echo "\n=== Testing Date Range Query ===\n\n";

// Test February 2026 query
$startDate = '2026-02-01';
$endDate = '2026-02-28';

echo "Querying for date range: $startDate to $endDate\n";

$febAppointments = Appointment::where('salon_id', 5)
    ->whereBetween('date', [$startDate, $endDate])
    ->get();

echo "Found: " . $febAppointments->count() . " appointments\n\n";

if ($febAppointments->count() > 0) {
    echo "Sample appointments:\n";
    foreach ($febAppointments->take(5) as $apt) {
        echo "  ID: {$apt->id}, Date: {$apt->date}, Time: {$apt->time}, Client: {$apt->client_name}\n";
    }
}

echo "\n=== Checking Date Column Type ===\n";
$result = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'appointments' AND column_name = 'date'");
print_r($result);

echo "\n=== Sample Raw Dates ===\n";
$sample = Appointment::where('salon_id', 5)->take(5)->get(['id', 'date']);
foreach ($sample as $apt) {
    echo "ID: {$apt->id}, Date (raw): {$apt->date}, Date (formatted): " . Carbon::parse($apt->date)->format('Y-m-d') . "\n";
}
