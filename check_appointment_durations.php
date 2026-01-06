<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Appointment;
use Carbon\Carbon;

echo "=== PROVJERA TRAJANJA TERMINA ===\n\n";

$appointments = Appointment::where('staff_id', 2)
    ->where('date', '2026-01-08')
    ->with('service')
    ->orderBy('time')
    ->get();

echo "Termini za Milenu 08.01.2026:\n\n";

foreach ($appointments as $apt) {
    $service = $apt->service;
    $start = Carbon::parse($apt->time);
    $end = Carbon::parse($apt->end_time);
    $actualDuration = $start->diffInMinutes($end);

    $mismatch = ($actualDuration != $service->duration) ? ' ⚠️ NEPODUDARANJE!' : '';

    echo "ID {$apt->id}: {$apt->time} - {$apt->end_time}\n";
    echo "  Usluga: {$service->name}\n";
    echo "  Trajanje usluge: {$service->duration} min\n";
    echo "  Stvarno trajanje: {$actualDuration} min{$mismatch}\n";
    echo "  Klijent: {$apt->client_name}\n";
    echo "\n";
}

echo "=== GOTOVO ===\n";
