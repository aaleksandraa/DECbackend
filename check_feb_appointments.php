<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Appointment;

echo "=== Checking Februar 2026 Appointments ===\n\n";

$feb = Appointment::whereBetween('date', ['2026-02-01', '2026-02-28'])->get();

echo "Total Februar 2026 appointments: " . $feb->count() . "\n\n";

if ($feb->count() > 0) {
    echo "Sample appointments:\n";
    foreach ($feb->take(10) as $a) {
        echo "ID: {$a->id}, Salon: {$a->salon_id}, Date: {$a->date}, Time: {$a->time}, Client: {$a->client_name}\n";
    }

    echo "\nBy salon:\n";
    $bySalon = $feb->groupBy('salon_id');
    foreach ($bySalon as $salonId => $appointments) {
        echo "  Salon $salonId: " . $appointments->count() . " appointments\n";
    }
}

// Also check with different date formats
echo "\n=== Checking with different queries ===\n";

$query1 = Appointment::where('date', '>=', '2026-02-01')->where('date', '<=', '2026-02-28')->count();
echo "Query 1 (>= and <=): $query1\n";

$query2 = Appointment::whereRaw("date >= '2026-02-01' AND date <= '2026-02-28'")->count();
echo "Query 2 (raw): $query2\n";

$query3 = Appointment::whereYear('date', 2026)->whereMonth('date', 2)->count();
echo "Query 3 (year/month): $query3\n";
