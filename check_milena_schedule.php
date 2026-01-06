<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Staff;
use App\Models\Appointment;
use Carbon\Carbon;

echo "=== Checking Milena Joviccc Schedule for 07.02.2026 ===\n\n";

// Find Milena
$milena = Staff::where('name', 'LIKE', '%Milena%')->first();

if (!$milena) {
    echo "Milena not found!\n";
    exit;
}

echo "Staff: {$milena->name} (ID: {$milena->id})\n";
echo "Salon ID: {$milena->salon_id}\n\n";

// Check working hours for Friday (07.02.2026 is Friday)
$date = Carbon::parse('2026-02-07');
$dayOfWeek = strtolower($date->format('l')); // 'friday'

echo "Day of week: $dayOfWeek\n\n";

echo "Working hours:\n";
$workingHours = $milena->working_hours;
print_r($workingHours);

echo "\n\nWorking hours for $dayOfWeek:\n";
if (isset($workingHours[$dayOfWeek])) {
    print_r($workingHours[$dayOfWeek]);
} else {
    echo "No working hours set for $dayOfWeek\n";
}

// Check for breaks
echo "\n\nStaff Breaks:\n";
$breaks = $milena->breaks()->whereRaw('is_active = true')->get();
foreach ($breaks as $break) {
    echo "Break: {$break->start_time} - {$break->end_time}\n";
    echo "  Type: {$break->type}\n";
    echo "  Start Date: {$break->start_date}\n";
    echo "  End Date: {$break->end_date}\n";
    echo "  Days: " . json_encode($break->days_of_week) . "\n\n";
}

// Check for vacations
echo "Staff Vacations:\n";
$vacations = $milena->vacations()->whereRaw('is_active = true')->get();
foreach ($vacations as $vacation) {
    echo "Vacation: {$vacation->start_date} - {$vacation->end_date}\n";
    echo "  Reason: {$vacation->reason}\n\n";
}

// Check salon breaks
echo "Salon Breaks:\n";
$salonBreaks = $milena->salon->salonBreaks()->whereRaw('is_active = true')->get();
foreach ($salonBreaks as $break) {
    echo "Break: {$break->start_time} - {$break->end_time}\n";
    echo "  Type: {$break->type}\n";
    if ($break->start_date) echo "  Start Date: {$break->start_date}\n";
    if ($break->end_date) echo "  End Date: {$break->end_date}\n";
    if ($break->days_of_week) echo "  Days: " . json_encode($break->days_of_week) . "\n";
    echo "\n";
}

// Check appointments on that date
echo "Appointments on 07.02.2026:\n";
$appointments = Appointment::where('staff_id', $milena->id)
    ->where('date', '2026-02-07')
    ->orderBy('time')
    ->get();

if ($appointments->count() > 0) {
    foreach ($appointments as $apt) {
        echo "  {$apt->time} - {$apt->end_time} ({$apt->status}): {$apt->client_name}\n";
    }
} else {
    echo "  No appointments\n";
}

echo "\n=== Checking Available Slots ===\n\n";

// Manually calculate slots
$workHours = $workingHours[$dayOfWeek] ?? null;
if ($workHours && $workHours['is_working']) {
    $start = $workHours['start'];
    $end = $workHours['end'];
    $interval = $milena->salon->booking_slot_interval ?? 30;

    echo "Working: $start - $end (interval: {$interval}min)\n\n";

    echo "All possible slots:\n";
    $startTime = strtotime($start);
    $endTime = strtotime($end);

    for ($time = $startTime; $time < $endTime; $time += $interval * 60) {
        $slot = date('H:i', $time);
        echo "  $slot\n";
    }
}
