<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Staff;
use App\Models\Salon;
use App\Services\SalonService;
use Carbon\Carbon;

echo "=== DEBUG: Zašto se 13:30 ne prikazuje u widgetu? ===\n\n";

// Pronađi Milenu
$milena = Staff::with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations'])
    ->where('name', 'LIKE', '%Milena%')
    ->first();

if (!$milena) {
    echo "❌ Milena nije pronađena!\n";
    exit;
}

$salon = $milena->salon;
$date = '08.01.2026';
$isoDate = '2026-01-08';
$dayOfWeek = 'thursday';

echo "Zaposlena: {$milena->name} (ID: {$milena->id})\n";
echo "Salon: {$salon->name} (ID: {$salon->id})\n";
echo "Datum: {$date} ({$dayOfWeek})\n\n";

// 1. Provjeri radno vrijeme
echo "=== 1. RADNO VRIJEME ===\n";

$salonHours = $salon->working_hours[$dayOfWeek] ?? null;
echo "Salon radno vrijeme:\n";
if ($salonHours) {
    echo "  is_open: " . ($salonHours['is_open'] ? 'true' : 'false') . "\n";
    echo "  open: {$salonHours['open']}\n";
    echo "  close: {$salonHours['close']}\n";
} else {
    echo "  ❌ Nije definisano\n";
}

$staffHours = $milena->working_hours[$dayOfWeek] ?? null;
echo "\nZaposlena radno vrijeme:\n";
if ($staffHours) {
    echo "  is_working: " . ($staffHours['is_working'] ? 'true' : 'false') . "\n";
    echo "  start: {$staffHours['start']}\n";
    echo "  end: {$staffHours['end']}\n";
} else {
    echo "  ❌ Nije definisano\n";
}

// 2. Provjeri da li je zaposlena dostupna u 13:30
echo "\n=== 2. PROVJERA DOSTUPNOSTI 13:30 ===\n";

$targetTime = '13:30';
$duration = 30; // Šišanje traje 30 minuta

echo "Provjeravam: {$targetTime} za trajanje {$duration} minuta\n";
echo "Kraj termina bi bio: " . Carbon::parse($targetTime)->addMinutes($duration)->format('H:i') . "\n\n";

// Pozovi isAvailable metodu
$isAvailable = $milena->isAvailable($date, $targetTime, $duration);

echo "Rezultat: " . ($isAvailable ? '✅ DOSTUPNO' : '❌ NIJE DOSTUPNO') . "\n\n";

// 3. Simuliraj widget poziv
echo "=== 3. SIMULACIJA WIDGET POZIVA ===\n";

$salonService = app(SalonService::class);

// Widget šalje jedan servis (Šišanje)
$services = [
    [
        'serviceId' => '1', // Pretpostavljam da je Šišanje ID 1
        'staffId' => $milena->id,
        'duration' => 30
    ]
];

echo "Pozivam getAvailableTimeSlotsForMultipleServices...\n";
echo "Parametri:\n";
echo "  - Datum: {$date}\n";
echo "  - Usluga: Šišanje (30 min)\n";
echo "  - Zaposlena: {$milena->name}\n\n";

$availableSlots = $salonService->getAvailableTimeSlotsForMultipleServices(
    $salon,
    $date,
    $services
);

echo "Broj dostupnih slotova: " . count($availableSlots) . "\n\n";

if (count($availableSlots) > 0) {
    echo "Dostupni slotovi:\n";
    foreach ($availableSlots as $slot) {
        $highlight = ($slot === '13:30') ? ' ← OVAJ TRAŽIMO!' : '';
        echo "  - {$slot}{$highlight}\n";
    }
} else {
    echo "❌ Nema dostupnih slotova!\n";
}

// 4. Provjeri da li 13:30 postoji u listi
echo "\n=== 4. DA LI JE 13:30 U LISTI? ===\n";

if (in_array('13:30', $availableSlots)) {
    echo "✅ DA - 13:30 JE dostupan u widgetu!\n";
} else {
    echo "❌ NE - 13:30 NIJE dostupan u widgetu!\n";
    echo "\nMoguće razloge:\n";

    // Provjeri da li je danas
    $isToday = ($isoDate === date('Y-m-d'));
    if ($isToday) {
        $currentTime = date('H:i');
        echo "  - Danas je {$isoDate}, trenutno vrijeme: {$currentTime}\n";
        if ('13:30' <= $currentTime) {
            echo "  ⚠️ 13:30 je u prošlosti!\n";
        }
    }

    // Provjeri termine
    $appointments = \App\Models\Appointment::where('staff_id', $milena->id)
        ->where('date', $isoDate)
        ->orderBy('time')
        ->get();

    echo "\n  Termini za taj dan:\n";
    foreach ($appointments as $apt) {
        $service = $apt->service;
        $start = Carbon::parse($apt->time);
        $end = $start->copy()->addMinutes($service->duration);

        $covers13_30 = ($start->format('H:i') <= '13:30' && $end->format('H:i') > '13:30');
        $marker = $covers13_30 ? ' ← POKRIVA 13:30!' : '';

        echo "    {$start->format('H:i')} - {$end->format('H:i')} ({$service->name}){$marker}\n";
    }
}

echo "\n=== GOTOVO ===\n";
