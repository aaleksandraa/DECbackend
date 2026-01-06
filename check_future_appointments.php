<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Appointment;

echo "=== Checking Future Appointments ===\n\n";

$jan2026 = Appointment::whereBetween('date', ['2026-01-01', '2026-01-31'])->count();
$feb2026 = Appointment::whereBetween('date', ['2026-02-01', '2026-02-28'])->count();
$mar2026 = Appointment::whereBetween('date', ['2026-03-01', '2026-03-31'])->count();

echo "Januar 2026: $jan2026 termina\n";
echo "Februar 2026: $feb2026 termina\n";
echo "Mart 2026: $mar2026 termina\n\n";

if ($feb2026 > 0) {
    echo "Sample Februar appointments:\n";
    $sample = Appointment::whereBetween('date', ['2026-02-01', '2026-02-28'])
        ->take(5)
        ->get(['id', 'date', 'time', 'client_name', 'salon_id']);
    foreach ($sample as $apt) {
        echo "  ID: {$apt->id}, Date: {$apt->date}, Time: {$apt->time}, Client: {$apt->client_name}, Salon: {$apt->salon_id}\n";
    }
}
