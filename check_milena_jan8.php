<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Staff;
use App\Models\Appointment;
use App\Models\StaffBreak;
use Carbon\Carbon;

echo "=== ANALIZA: 08.01.2026 - Milena Jovic ===\n\n";

// Pronađi Milenu
$milena = Staff::where('name', 'LIKE', '%Milena%')->first();

if (!$milena) {
    echo "❌ Milena nije pronađena!\n";
    exit;
}

echo "✅ Pronađena: {$milena->name} (ID: {$milena->id})\n";
echo "Salon ID: {$milena->salon_id}\n\n";

// Datum koji istražujemo
$date = '2026-01-08';
$dayOfWeek = Carbon::parse($date)->locale('bs')->dayName;

echo "📅 Datum: {$date} ({$dayOfWeek})\n\n";

// 1. Provjeri radno vrijeme
echo "=== 1. RADNO VRIJEME ===\n";
$workingHours = $milena->working_hours;
$dayKey = strtolower(Carbon::parse($date)->englishDayOfWeek);
echo "Dan: {$dayKey}\n";
echo "Raw working hours: " . json_encode($workingHours) . "\n";

if (isset($workingHours[$dayKey])) {
    $hours = $workingHours[$dayKey];
    echo "Radi: " . (isset($hours['is_open']) && $hours['is_open'] ? 'DA' : 'NE') . "\n";
    if (isset($hours['is_open']) && $hours['is_open']) {
        echo "Vrijeme: " . ($hours['open'] ?? 'N/A') . " - " . ($hours['close'] ?? 'N/A') . "\n";
    }
} else {
    echo "⚠️ Nema definisano radno vrijeme za ovaj dan\n";
}

echo "\n=== 2. TERMINI ZA 08.01.2026 ===\n";
$appointments = Appointment::where('staff_id', $milena->id)
    ->where('date', $date)
    ->orderBy('time')
    ->get();

echo "Broj termina: " . $appointments->count() . "\n\n";

foreach ($appointments as $apt) {
    $service = $apt->service;
    $endTime = Carbon::parse($apt->time)->addMinutes($service->duration)->format('H:i');

    echo "Termin ID: {$apt->id}\n";
    echo "  Vrijeme: {$apt->time} - {$endTime}\n";
    echo "  Usluga: {$service->name} (trajanje: {$service->duration} min)\n";
    echo "  Klijent: {$apt->client_name}\n";
    echo "  Status: {$apt->status}\n";
    echo "  Kreiran: {$apt->created_at}\n";
    echo "\n";
}

// 3. Provjeri pauze
echo "=== 3. PAUZE ZA 08.01.2026 ===\n";
$breaks = StaffBreak::where('staff_id', $milena->id)
    ->where('date', $date)
    ->get();

echo "Broj pauza: " . $breaks->count() . "\n\n";

foreach ($breaks as $break) {
    echo "Pauza ID: {$break->id}\n";
    echo "  Vrijeme: {$break->start_time} - {$break->end_time}\n";
    echo "  Razlog: {$break->reason}\n";
    echo "  Kreirana: {$break->created_at}\n";
    echo "\n";
}

// 4. Provjeri dostupne slotove (kao što widget radi)
echo "=== 4. DOSTUPNI SLOTOVI (WIDGET LOGIKA) ===\n";

$workingHours = $milena->working_hours;
$dayKey = strtolower(Carbon::parse($date)->englishDayOfWeek);

if (!isset($workingHours[$dayKey]) || !isset($workingHours[$dayKey]['is_open']) || !$workingHours[$dayKey]['is_open']) {
    echo "❌ Ne radi ovaj dan\n";
} else {
    $startTime = Carbon::parse($date . ' ' . $workingHours[$dayKey]['open']);
    $endTime = Carbon::parse($date . ' ' . $workingHours[$dayKey]['close']);

    echo "Radno vrijeme: {$startTime->format('H:i')} - {$endTime->format('H:i')}\n";
    echo "Interval slotova: 30 minuta\n\n";

    // Generiši sve slotove
    $allSlots = [];
    $current = $startTime->copy();

    while ($current->lt($endTime)) {
        $allSlots[] = $current->format('H:i');
        $current->addMinutes(30);
    }

    echo "Svi mogući slotovi:\n";
    echo implode(', ', $allSlots) . "\n\n";

    // Provjeri koji su zauzeti
    echo "Zauzeti slotovi:\n";
    foreach ($appointments as $apt) {
        $service = $apt->service;
        $aptStart = Carbon::parse($apt->time);
        $aptEnd = $aptStart->copy()->addMinutes($service->duration);

        echo "  {$aptStart->format('H:i')} - {$aptEnd->format('H:i')} ({$service->name})\n";
    }

    echo "\nPauze:\n";
    foreach ($breaks as $break) {
        echo "  {$break->start_time} - {$break->end_time}\n";
    }
}

// 5. Provjeri šta se dešava oko 13:30
echo "\n=== 5. ANALIZA VREMENA 13:30-14:00 ===\n";

$targetTime = '13:30';
$targetEnd = '14:00';

echo "Provjeravam da li je slot 13:30 dostupan...\n\n";

// Provjeri da li postoji termin koji pokriva 13:30
$conflictingAppointment = null;
foreach ($appointments as $apt) {
    $service = $apt->service;
    $aptStart = Carbon::parse($apt->time);
    $aptEnd = $aptStart->copy()->addMinutes($service->duration);
    $checkTime = Carbon::parse($targetTime);

    if ($checkTime->gte($aptStart) && $checkTime->lt($aptEnd)) {
        $conflictingAppointment = $apt;
        break;
    }
}

if ($conflictingAppointment) {
    $service = $conflictingAppointment->service;
    $aptStart = Carbon::parse($conflictingAppointment->time);
    $aptEnd = $aptStart->copy()->addMinutes($service->duration);

    echo "❌ ZAUZETO - Postoji termin:\n";
    echo "  ID: {$conflictingAppointment->id}\n";
    echo "  Vrijeme: {$aptStart->format('H:i')} - {$aptEnd->format('H:i')}\n";
    echo "  Usluga: {$service->name}\n";
    echo "  Klijent: {$conflictingAppointment->client_name}\n";
} else {
    echo "✅ SLOBODNO - Nema termina koji pokriva 13:30\n";
}

// Provjeri pauze
$conflictingBreak = null;
foreach ($breaks as $break) {
    $breakStart = Carbon::parse($break->start_time);
    $breakEnd = Carbon::parse($break->end_time);
    $checkTime = Carbon::parse($targetTime);

    if ($checkTime->gte($breakStart) && $checkTime->lt($breakEnd)) {
        $conflictingBreak = $break;
        break;
    }
}

if ($conflictingBreak) {
    echo "❌ PAUZA - Postoji pauza:\n";
    echo "  Vrijeme: {$conflictingBreak->start_time} - {$conflictingBreak->end_time}\n";
    echo "  Razlog: {$conflictingBreak->reason}\n";
} else {
    echo "✅ Nema pauze u 13:30\n";
}

echo "\n=== 6. ZAKLJUČAK ===\n";

if (!$conflictingAppointment && !$conflictingBreak) {
    echo "🤔 MISTERIJA: Slot 13:30 TREBA biti dostupan!\n";
    echo "Moguće razloge:\n";
    echo "  1. Widget koristi drugačiju logiku za provjeru dostupnosti\n";
    echo "  2. Postoji neki drugi filter (npr. minimalno trajanje usluge)\n";
    echo "  3. Problem sa timezone-om\n";
    echo "  4. Cache problem\n";
} else {
    echo "✅ Slot 13:30 je zauzet, što je očekivano\n";
}

echo "\n=== GOTOVO ===\n";
