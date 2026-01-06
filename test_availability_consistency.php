<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Staff;
use App\Models\Salon;
use App\Services\SalonService;
use Carbon\Carbon;

echo "=== TEST KONZISTENTNOSTI DOSTUPNOSTI TERMINA ===\n\n";

// Pronađi Milenu
$milena = Staff::with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations', 'appointments'])
    ->where('name', 'LIKE', '%Milena%')
    ->first();

if (!$milena) {
    echo "❌ Milena nije pronađena!\n";
    exit;
}

$salon = $milena->salon;
$date = '08.01.2026';
$isoDate = '2026-01-08';

echo "Zaposlena: {$milena->name} (ID: {$milena->id})\n";
echo "Salon: {$salon->name} (ID: {$salon->id})\n";
echo "Datum: {$date}\n\n";

// Test 1: Pozovi 10 puta i provjeri konzistentnost
echo "=== TEST 1: KONZISTENTNOST (10 poziva) ===\n";

$salonService = app(SalonService::class);
$services = [
    [
        'serviceId' => '1',
        'staffId' => $milena->id,
        'duration' => 30
    ]
];

$results = [];
for ($i = 1; $i <= 10; $i++) {
    $slots = $salonService->getAvailableTimeSlotsForMultipleServices(
        $salon,
        $date,
        $services
    );

    $slotsStr = implode(', ', $slots);
    $results[] = $slotsStr;

    echo "Poziv #{$i}: " . (count($slots) > 0 ? count($slots) . " slotova" : "NEMA SLOTOVA") . "\n";
    if (count($slots) > 0) {
        echo "  Slotovi: {$slotsStr}\n";
    }
}

// Provjeri da li su svi rezultati isti
$uniqueResults = array_unique($results);
if (count($uniqueResults) === 1) {
    echo "\n✅ KONZISTENTNO - Svi pozivi vraćaju iste rezultate!\n";
} else {
    echo "\n❌ NEKONZISTENTNO - Različiti rezultati!\n";
    echo "Različite verzije:\n";
    foreach ($uniqueResults as $idx => $result) {
        $count = count(array_filter($results, fn($r) => $r === $result));
        echo "  Verzija " . ($idx + 1) . " ({$count}x): {$result}\n";
    }
}

// Test 2: Provjeri svaki slot pojedinačno
echo "\n=== TEST 2: PROVJERA SVAKOG SLOTA ===\n";

$testSlots = ['11:30', '13:30', '14:30', '15:00'];

foreach ($testSlots as $slot) {
    echo "\nSlot {$slot}:\n";

    // Test 5 puta
    $available = [];
    for ($i = 1; $i <= 5; $i++) {
        // Reload staff to avoid caching
        $freshStaff = Staff::with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations', 'appointments'])
            ->find($milena->id);

        $isAvailable = $freshStaff->isAvailable($date, $slot, 30);
        $available[] = $isAvailable;

        echo "  Test #{$i}: " . ($isAvailable ? '✅ Dostupno' : '❌ Zauzeto') . "\n";
    }

    $uniqueAvailable = array_unique($available);
    if (count($uniqueAvailable) === 1) {
        echo "  Rezultat: " . ($available[0] ? '✅ KONZISTENTNO DOSTUPAN' : '❌ KONZISTENTNO ZAUZET') . "\n";
    } else {
        echo "  ⚠️ NEKONZISTENTNO - Različiti rezultati!\n";
    }
}

// Test 3: Provjeri termine u bazi
echo "\n=== TEST 3: TERMINI U BAZI ===\n";

$appointments = \App\Models\Appointment::where('staff_id', $milena->id)
    ->where('date', $isoDate)
    ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
    ->orderBy('time')
    ->get();

echo "Broj termina: " . $appointments->count() . "\n\n";

$occupiedSlots = [];
foreach ($appointments as $apt) {
    $service = $apt->service;
    $start = Carbon::parse($apt->time);
    $end = $start->copy()->addMinutes($service->duration);

    echo "{$start->format('H:i')} - {$end->format('H:i')} ({$service->name}, {$apt->client_name})\n";

    // Markiraj sve slotove koje ovaj termin zauzima
    $current = $start->copy();
    while ($current->lt($end)) {
        $occupiedSlots[] = $current->format('H:i');
        $current->addMinutes(30);
    }
}

// Test 4: Uporedi sa očekivanim slobodnim slotovima
echo "\n=== TEST 4: OČEKIVANI VS STVARNI SLOTOVI ===\n";

// Generiši sve moguće slotove
$allSlots = [];
$current = Carbon::parse('09:00');
$endTime = Carbon::parse('17:00');

while ($current->lt($endTime)) {
    $allSlots[] = $current->format('H:i');
    $current->addMinutes(30);
}

echo "Svi mogući slotovi (09:00-17:00, interval 30min):\n";
echo implode(', ', $allSlots) . "\n\n";

echo "Zauzeti slotovi:\n";
$uniqueOccupied = array_unique($occupiedSlots);
sort($uniqueOccupied);
echo implode(', ', $uniqueOccupied) . "\n\n";

$expectedFreeSlots = array_diff($allSlots, $uniqueOccupied);
echo "Očekivani slobodni slotovi:\n";
echo implode(', ', $expectedFreeSlots) . "\n\n";

// Pozovi API i uporedi
$actualSlots = $salonService->getAvailableTimeSlotsForMultipleServices(
    $salon,
    $date,
    $services
);

echo "Stvarni slobodni slotovi (iz API-ja):\n";
echo implode(', ', $actualSlots) . "\n\n";

// Uporedi
$missing = array_diff($expectedFreeSlots, $actualSlots);
$extra = array_diff($actualSlots, $expectedFreeSlots);

if (empty($missing) && empty($extra)) {
    echo "✅ PERFEKTNO - Očekivani i stvarni slotovi se poklapaju!\n";
} else {
    if (!empty($missing)) {
        echo "⚠️ NEDOSTAJU slotovi (trebaju biti dostupni ali nisu):\n";
        echo "  " . implode(', ', $missing) . "\n";
    }
    if (!empty($extra)) {
        echo "⚠️ DODATNI slotovi (prikazuju se ali ne bi trebali):\n";
        echo "  " . implode(', ', $extra) . "\n";
    }
}

// Test 5: Provjeri cache i relationship loading
echo "\n=== TEST 5: CACHE I RELATIONSHIP LOADING ===\n";

// Test bez eager loading
$staff1 = Staff::find($milena->id);
$result1 = $staff1->isAvailable($date, '13:30', 30);
echo "Bez eager loading: " . ($result1 ? '✅ Dostupno' : '❌ Zauzeto') . "\n";

// Test sa eager loading
$staff2 = Staff::with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations', 'appointments'])
    ->find($milena->id);
$result2 = $staff2->isAvailable($date, '13:30', 30);
echo "Sa eager loading: " . ($result2 ? '✅ Dostupno' : '❌ Zauzeto') . "\n";

if ($result1 === $result2) {
    echo "✅ Konzistentno\n";
} else {
    echo "❌ PROBLEM - Različiti rezultati zavisno od eager loading-a!\n";
}

// Test 6: Provjeri datum format
echo "\n=== TEST 6: DATUM FORMAT ===\n";

$formats = [
    '08.01.2026' => 'DD.MM.YYYY (European)',
    '2026-01-08' => 'YYYY-MM-DD (ISO)',
];

foreach ($formats as $dateFormat => $label) {
    $result = $milena->isAvailable($dateFormat, '13:30', 30);
    echo "{$label}: " . ($result ? '✅ Dostupno' : '❌ Zauzeto') . "\n";
}

echo "\n=== GOTOVO ===\n";
