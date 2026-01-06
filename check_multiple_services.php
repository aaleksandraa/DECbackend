<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Appointment;
use Carbon\Carbon;

echo "=== PROVJERA TERMINA SA VIŠE USLUGA ===\n\n";

// Provjeri termine koji imaju netačno trajanje
$problematicIds = [14964, 14987];

foreach ($problematicIds as $id) {
    $apt = Appointment::with(['services', 'service'])->find($id);

    if (!$apt) {
        echo "❌ Termin ID {$id} nije pronađen\n\n";
        continue;
    }

    echo "=== TERMIN ID {$id} ===\n";
    echo "Klijent: {$apt->client_name}\n";
    echo "Datum: {$apt->date}\n";
    echo "Vrijeme: {$apt->time} - {$apt->end_time}\n";

    $start = Carbon::parse($apt->time);
    $end = Carbon::parse($apt->end_time);
    $actualDuration = $start->diffInMinutes($end);

    echo "Stvarno trajanje: {$actualDuration} min\n\n";

    // Provjeri service (singular - stara kolona)
    if ($apt->service) {
        echo "Glavna usluga (service_id):\n";
        echo "  - {$apt->service->name} ({$apt->service->duration} min)\n\n";
    }

    // Provjeri services (plural - many-to-many)
    if ($apt->services && $apt->services->count() > 0) {
        echo "Sve usluge (services - many-to-many):\n";
        $totalDuration = 0;
        foreach ($apt->services as $service) {
            echo "  - {$service->name} ({$service->duration} min)\n";
            $totalDuration += $service->duration;
        }
        echo "\nUkupno trajanje svih usluga: {$totalDuration} min\n";

        if ($totalDuration === $actualDuration) {
            echo "✅ POKLAPA SE - Trajanje termina odgovara zbiru svih usluga!\n";
        } else {
            echo "⚠️ NEPODUDARANJE - Trajanje termina ({$actualDuration} min) != Zbir usluga ({$totalDuration} min)\n";
        }
    } else {
        echo "Nema dodatnih usluga (services tabela prazna)\n";
    }

    echo "\n" . str_repeat('-', 60) . "\n\n";
}

// Provjeri sve termine za 08.01.2026
echo "=== SVI TERMINI ZA 08.01.2026 (Milena) ===\n\n";

$appointments = Appointment::with(['services', 'service'])
    ->where('staff_id', 2)
    ->where('date', '2026-01-08')
    ->orderBy('time')
    ->get();

foreach ($appointments as $apt) {
    $start = Carbon::parse($apt->time);
    $end = Carbon::parse($apt->end_time);
    $actualDuration = $start->diffInMinutes($end);

    $servicesCount = $apt->services ? $apt->services->count() : 0;
    $totalServicesDuration = $apt->services ? $apt->services->sum('duration') : 0;

    $marker = '';
    if ($servicesCount > 1) {
        $marker = " 🔄 {$servicesCount} usluge";
    } elseif ($servicesCount === 1) {
        $marker = " ✓ 1 usluga";
    }

    echo "ID {$apt->id}: {$apt->time}-{$apt->end_time} ({$actualDuration} min) - {$apt->client_name}{$marker}\n";

    if ($servicesCount > 0) {
        foreach ($apt->services as $service) {
            echo "  └─ {$service->name} ({$service->duration} min)\n";
        }

        if ($totalServicesDuration !== $actualDuration) {
            echo "  ⚠️ Nepodudaranje: Zbir usluga ({$totalServicesDuration} min) != Trajanje termina ({$actualDuration} min)\n";
        }
    }
}

echo "\n=== GOTOVO ===\n";
