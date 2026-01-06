<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Appointment;
use Carbon\Carbon;

echo "=== TERMINI SA DODATNIM USLUGAMA U NOTES ===\n\n";

$appointments = Appointment::where('notes', 'LIKE', '%Dodatne usluge%')
    ->orWhere('notes', 'LIKE', '%dodatne usluge%')
    ->with('service')
    ->orderBy('date')
    ->orderBy('time')
    ->get();

echo "Pronađeno: " . $appointments->count() . " termina\n\n";

foreach ($appointments as $apt) {
    $start = Carbon::parse($apt->time);
    $end = Carbon::parse($apt->end_time);
    $actualDuration = $start->diffInMinutes($end);

    echo "ID {$apt->id}: {$apt->date} {$apt->time}-{$apt->end_time}\n";
    echo "  Klijent: {$apt->client_name}\n";
    echo "  Glavna usluga: {$apt->service->name} ({$apt->service->duration} min)\n";
    echo "  Stvarno trajanje: {$actualDuration} min\n";
    echo "  Notes: {$apt->notes}\n";

    $extraDuration = $actualDuration - $apt->service->duration;
    if ($extraDuration > 0) {
        echo "  ➕ Dodatno vrijeme: {$extraDuration} min\n";
    }

    echo "\n";
}

// Provjeri specifično za 08.01.2026
echo "=== TERMINI ZA 08.01.2026 SA DODATNIM USLUGAMA ===\n\n";

$jan8 = Appointment::where('date', '2026-01-08')
    ->where('staff_id', 2)
    ->where(function($q) {
        $q->where('notes', 'LIKE', '%Dodatne usluge%')
          ->orWhere('notes', 'LIKE', '%dodatne usluge%');
    })
    ->with('service')
    ->orderBy('time')
    ->get();

if ($jan8->count() > 0) {
    foreach ($jan8 as $apt) {
        $start = Carbon::parse($apt->time);
        $end = Carbon::parse($apt->end_time);
        $actualDuration = $start->diffInMinutes($end);

        echo "ID {$apt->id}: {$apt->time}-{$apt->end_time} ({$actualDuration} min)\n";
        echo "  {$apt->service->name} ({$apt->service->duration} min) + dodatno " . ($actualDuration - $apt->service->duration) . " min\n";
        echo "  Notes: {$apt->notes}\n\n";
    }
} else {
    echo "Nema termina sa dodatnim uslugama za 08.01.2026\n";
}

echo "=== GOTOVO ===\n";
