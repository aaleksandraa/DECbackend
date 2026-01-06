<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Staff;
use Carbon\Carbon;

echo "=== DEBUG: Zašto nedostaju slotovi 11:30, 14:30, 15:00? ===\n\n";

$milena = Staff::with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations', 'appointments'])
    ->where('name', 'LIKE', '%Milena%')
    ->first();

$date = '08.01.2026';
$isoDate = '2026-01-08';
$missingSlots = ['11:30', '14:30', '15:00'];

foreach ($missingSlots as $slot) {
    echo "=== SLOT: {$slot} ===\n";

    $duration = 30;
    $appointmentTime = strtotime($slot);
    $appointmentEndTime = strtotime("+{$duration} minutes", $appointmentTime);

    echo "Početak: {$slot}\n";
    echo "Kraj: " . date('H:i', $appointmentEndTime) . "\n";
    echo "Trajanje: {$duration} min\n\n";

    // Provjeri termine
    $appointments = \App\Models\Appointment::where('staff_id', $milena->id)
        ->where('date', $isoDate)
        ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
        ->get();

    echo "Provjera preklapanja sa postojećim terminima:\n";

    $hasConflict = false;
    foreach ($appointments as $apt) {
        $service = $apt->service;
        $existingStart = strtotime($apt->time);
        $existingEnd = strtotime($apt->end_time);

        // Provjeri preklapanje
        $overlaps = ($appointmentTime < $existingEnd) && ($appointmentEndTime > $existingStart);

        if ($overlaps) {
            $hasConflict = true;
            echo "  ❌ KONFLIKT sa terminom ID {$apt->id}:\n";
            echo "     Postojeći: {$apt->time} - {$apt->end_time} ({$service->name})\n";
            echo "     Novi: {$slot} - " . date('H:i', $appointmentEndTime) . "\n";
            echo "     Razlog: ";

            if ($appointmentTime >= $existingStart && $appointmentTime < $existingEnd) {
                echo "Početak novog termina ({$slot}) je unutar postojećeg termina\n";
            } elseif ($appointmentEndTime > $existingStart && $appointmentEndTime <= $existingEnd) {
                echo "Kraj novog termina (" . date('H:i', $appointmentEndTime) . ") je unutar postojećeg termina\n";
            } elseif ($appointmentTime <= $existingStart && $appointmentEndTime >= $existingEnd) {
                echo "Novi termin potpuno pokriva postojeći termin\n";
            }
        }
    }

    if (!$hasConflict) {
        echo "  ✅ Nema konflikta sa postojećim terminima\n";
    }

    // Provjeri radno vrijeme
    $dayOfWeek = 'thursday';
    $staffHours = $milena->working_hours[$dayOfWeek];
    $startTime = strtotime($staffHours['start']);
    $endTime = strtotime($staffHours['end']);

    echo "\nRadno vrijeme:\n";
    echo "  Početak: {$staffHours['start']}\n";
    echo "  Kraj: {$staffHours['end']}\n";

    if ($appointmentTime < $startTime) {
        echo "  ❌ Slot počinje prije radnog vremena\n";
    } elseif ($appointmentEndTime > $endTime) {
        echo "  ❌ Slot završava nakon radnog vremena\n";
    } else {
        echo "  ✅ Unutar radnog vremena\n";
    }

    // Pozovi isAvailable
    echo "\nRezultat isAvailable(): ";
    $isAvailable = $milena->isAvailable($date, $slot, $duration);
    echo ($isAvailable ? '✅ DOSTUPNO' : '❌ ZAUZETO') . "\n";

    echo "\n" . str_repeat('-', 60) . "\n\n";
}

// Provjeri SalonService logiku
echo "=== PROVJERA SalonService LOGIKE ===\n\n";

$salon = $milena->salon;
$salonService = app(\App\Services\SalonService::class);

$services = [
    [
        'serviceId' => '1',
        'staffId' => $milena->id,
        'duration' => 30
    ]
];

// Provjeri efektivno radno vrijeme
$dayOfWeek = 'thursday';
$salonHours = $salon->working_hours[$dayOfWeek];
$staffHours = $milena->working_hours[$dayOfWeek];

echo "Salon radno vrijeme: {$salonHours['open']} - {$salonHours['close']}\n";
echo "Staff radno vrijeme: {$staffHours['start']} - {$staffHours['end']}\n";

$effectiveStart = max($salonHours['open'], $staffHours['start']);
$effectiveEnd = min($salonHours['close'], $staffHours['end']);

echo "Efektivno radno vrijeme: {$effectiveStart} - {$effectiveEnd}\n\n";

// Provjeri latest start time
$totalDuration = 30;
$endTimeTimestamp = strtotime($effectiveEnd);
$latestStartTime = date('H:i', strtotime("-{$totalDuration} minutes", $endTimeTimestamp));

echo "Ukupno trajanje: {$totalDuration} min\n";
echo "Kraj radnog vremena: {$effectiveEnd}\n";
echo "Najkasnije vrijeme početka: {$latestStartTime}\n\n";

// Generiši potencijalne slotove
$interval = $salon->booking_slot_interval ?? 30;
echo "Interval slotova: {$interval} min\n\n";

echo "Generisanje slotova od {$effectiveStart} do {$latestStartTime}...\n";

$potentialSlots = [];
$current = Carbon::parse($effectiveStart);
$latest = Carbon::parse($latestStartTime);

while ($current->lte($latest)) {
    $potentialSlots[] = $current->format('H:i');
    $current->addMinutes($interval);
}

echo "Potencijalni slotovi: " . implode(', ', $potentialSlots) . "\n\n";

// Filtriraj dostupne
echo "Filtriranje dostupnih slotova...\n";
$availableSlots = [];

$isToday = ($isoDate === date('Y-m-d'));
$currentTime = date('H:i');

foreach ($potentialSlots as $slot) {
    echo "  Slot {$slot}: ";

    // Skip past slots for today
    if ($isToday && $slot <= $currentTime) {
        echo "❌ U prošlosti\n";
        continue;
    }

    // Check if staff is available
    if ($milena->isAvailable($date, $slot, $totalDuration)) {
        echo "✅ Dostupan\n";
        $availableSlots[] = $slot;
    } else {
        echo "❌ Zauzet\n";
    }
}

echo "\nFinalni dostupni slotovi: " . implode(', ', $availableSlots) . "\n";

echo "\n=== GOTOVO ===\n";
