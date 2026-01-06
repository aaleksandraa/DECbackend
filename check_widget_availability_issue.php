<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Appointment;
use App\Models\Staff;
use Carbon\Carbon;

echo "=== WIDGET AVAILABILITY ISSUE DEBUG ===\n\n";

// Scenario: Termin u 10:00 (30min), pokušaj zakazivanja u 10:30
$testDate = '02.01.2026'; // Prilagodi datum
$testStaffId = 1; // Prilagodi staff ID

echo "Test scenario:\n";
echo "- Postojeći termin: 10:00 (30min, završava u 10:30)\n";
echo "- Pokušaj zakazivanja: 10:30\n";
echo "- Očekivano: 10:30 treba biti DOSTUPAN\n\n";

// Convert date
$isoDate = Carbon::createFromFormat('d.m.Y', $testDate)->format('Y-m-d');

echo "1. Provjera postojećih termina za datum $testDate ($isoDate):\n";
$appointments = Appointment::whereDate('date', $isoDate)
    ->where('staff_id', $testStaffId)
    ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
    ->orderBy('time')
    ->get();

if ($appointments->isEmpty()) {
    echo "   ❌ Nema termina za ovaj datum\n";
    echo "   Kreiraj test termin:\n";
    echo "   - Datum: $testDate\n";
    echo "   - Vrijeme: 10:00\n";
    echo "   - Trajanje: 30min\n";
    echo "   - end_time: 10:30\n\n";
} else {
    foreach ($appointments as $apt) {
        echo "   Termin #{$apt->id}:\n";
        echo "   - time: {$apt->time}\n";
        echo "   - end_time: {$apt->end_time}\n";
        echo "   - service_id: {$apt->service_id}\n";
        echo "   - service_ids: " . json_encode($apt->service_ids) . "\n";
        echo "   - status: {$apt->status}\n";

        // Calculate what end_time should be
        if ($apt->service_id) {
            $service = \App\Models\Service::find($apt->service_id);
            if ($service) {
                $expectedEnd = Carbon::parse($apt->time)->addMinutes($service->duration)->format('H:i');
                echo "   - service duration: {$service->duration}min\n";
                echo "   - expected end_time: $expectedEnd\n";

                if ($apt->end_time !== $expectedEnd) {
                    echo "   ⚠️  PROBLEM: end_time ne odgovara trajanju usluge!\n";
                }
            }
        }

        if ($apt->service_ids && is_array($apt->service_ids)) {
            $services = \App\Models\Service::whereIn('id', $apt->service_ids)->get();
            $totalDuration = $services->sum('duration');
            $expectedEnd = Carbon::parse($apt->time)->addMinutes($totalDuration)->format('H:i');
            echo "   - total duration (multi-service): {$totalDuration}min\n";
            echo "   - expected end_time: $expectedEnd\n";

            if ($apt->end_time !== $expectedEnd) {
                echo "   ⚠️  PROBLEM: end_time ne odgovara ukupnom trajanju usluga!\n";
            }
        }

        echo "\n";
    }
}

echo "\n2. Test isAvailable() za 10:30:\n";
$staff = Staff::with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations', 'appointments'])
    ->find($testStaffId);

if (!$staff) {
    echo "   ❌ Staff ne postoji\n";
    exit(1);
}

$testTime = '10:30';
$testDuration = 30;

echo "   Pozivam: staff->isAvailable('$testDate', '$testTime', $testDuration)\n";
$isAvailable = $staff->isAvailable($testDate, $testTime, $testDuration);

echo "   Rezultat: " . ($isAvailable ? "✅ DOSTUPAN" : "❌ NIJE DOSTUPAN") . "\n\n";

if (!$isAvailable) {
    echo "3. Analiza zašto nije dostupan:\n";

    // Manual overlap check
    $appointmentTime = strtotime($testTime);
    $appointmentEndTime = strtotime("+{$testDuration} minutes", $appointmentTime);

    echo "   Traženi slot:\n";
    echo "   - Start: $testTime (" . date('H:i', $appointmentTime) . ")\n";
    echo "   - End: " . date('H:i', $appointmentEndTime) . "\n\n";

    foreach ($appointments as $apt) {
        $existingStart = strtotime($apt->time);
        $existingEnd = strtotime($apt->end_time);

        echo "   Postojeći termin #{$apt->id}:\n";
        echo "   - Start: {$apt->time} (timestamp: $existingStart)\n";
        echo "   - End: {$apt->end_time} (timestamp: $existingEnd)\n";

        // Overlap check
        $overlaps = ($appointmentTime < $existingEnd) && ($appointmentEndTime > $existingStart);

        echo "   - Provjera preklapanja:\n";
        echo "     ($appointmentTime < $existingEnd) = " . ($appointmentTime < $existingEnd ? 'true' : 'false') . "\n";
        echo "     ($appointmentEndTime > $existingStart) = " . ($appointmentEndTime > $existingStart ? 'true' : 'false') . "\n";
        echo "     Overlaps: " . ($overlaps ? "DA ❌" : "NE ✅") . "\n\n";

        if ($overlaps) {
            echo "   ⚠️  PROBLEM: Termin u 10:00-10:30 blokira slot 10:30-11:00\n";
            echo "   RAZLOG: Logika preklapanja je:\n";
            echo "   - (10:30 < 10:30) && (11:00 > 10:00)\n";
            echo "   - (false) && (true) = false\n";
            echo "   - Ovo je ISPRAVNO! Slot 10:30 TREBA biti dostupan!\n\n";
            echo "   Moguć uzrok:\n";
            echo "   - end_time u bazi je POGREŠAN (npr. 10:31 umjesto 10:30)\n";
            echo "   - Ili postoji drugi termin koji se preklapa\n";
        }
    }
}

echo "\n4. Rješenje:\n";
echo "   Ako end_time nije ispravan, pokreni:\n";
echo "   php backend/fix_multi_service_end_times.php\n\n";

echo "=== KRAJ DEBUG-a ===\n";
