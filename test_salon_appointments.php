<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Appointment;
use App\Models\Salon;
use Carbon\Carbon;

echo "=== Checking Salon and Appointments ===\n\n";

// Check salon 5
$salon = Salon::find(5);
if ($salon) {
    echo "Salon 5: {$salon->name}\n";
    echo "Owner ID: {$salon->owner_id}\n\n";
} else {
    echo "Salon 5 not found!\n\n";
}

// Get all appointments grouped by salon_id
echo "Appointments by salon_id:\n";
$appointmentsBySalon = Appointment::selectRaw('salon_id, COUNT(*) as count')
    ->groupBy('salon_id')
    ->get();

foreach ($appointmentsBySalon as $group) {
    $salonName = Salon::find($group->salon_id)?->name ?? 'Unknown';
    echo "  Salon {$group->salon_id} ({$salonName}): {$group->count} appointments\n";
}

echo "\n=== Recent Appointments (any salon) ===\n";
$recent = Appointment::orderBy('created_at', 'desc')
    ->take(10)
    ->get(['id', 'salon_id', 'date', 'time', 'client_name', 'created_at']);

foreach ($recent as $apt) {
    $salonName = Salon::find($apt->salon_id)?->name ?? 'Unknown';
    echo "ID: {$apt->id}, Salon: {$apt->salon_id} ({$salonName}), Date: {$apt->date}, Client: {$apt->client_name}\n";
}

echo "\n=== Checking for February 2026 appointments (any salon) ===\n";
$feb2026 = Appointment::whereBetween('date', ['2026-02-01', '2026-02-28'])
    ->get(['id', 'salon_id', 'date', 'time', 'client_name']);

echo "Found: " . $feb2026->count() . " appointments in February 2026\n";
if ($feb2026->count() > 0) {
    foreach ($feb2026->take(5) as $apt) {
        $salonName = Salon::find($apt->salon_id)?->name ?? 'Unknown';
        echo "  ID: {$apt->id}, Salon: {$apt->salon_id} ({$salonName}), Date: {$apt->date}, Time: {$apt->time}\n";
    }
}
