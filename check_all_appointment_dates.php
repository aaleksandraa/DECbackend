<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Appointment;
use Carbon\Carbon;

echo "=== All Appointments Date Range ===\n\n";

$total = Appointment::count();
echo "Total appointments in database: $total\n\n";

if ($total > 0) {
    $earliest = Appointment::orderBy('date', 'asc')->first();
    $latest = Appointment::orderBy('date', 'desc')->first();

    echo "Earliest appointment: {$earliest->date} (ID: {$earliest->id})\n";
    echo "Latest appointment: {$latest->date} (ID: {$latest->id})\n\n";

    // Group by year-month
    echo "Appointments by month:\n";
    $appointments = Appointment::orderBy('date', 'asc')->get(['date', 'salon_id']);

    $byMonth = [];
    foreach ($appointments as $apt) {
        $date = Carbon::parse($apt->date);
        $monthKey = $date->format('Y-m');

        if (!isset($byMonth[$monthKey])) {
            $byMonth[$monthKey] = ['total' => 0, 'by_salon' => []];
        }
        $byMonth[$monthKey]['total']++;

        if (!isset($byMonth[$monthKey]['by_salon'][$apt->salon_id])) {
            $byMonth[$monthKey]['by_salon'][$apt->salon_id] = 0;
        }
        $byMonth[$monthKey]['by_salon'][$apt->salon_id]++;
    }

    foreach ($byMonth as $month => $data) {
        echo "\n$month: {$data['total']} appointments\n";
        foreach ($data['by_salon'] as $salonId => $count) {
            echo "  Salon $salonId: $count\n";
        }
    }
}
