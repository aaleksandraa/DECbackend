<?php

/**
 * Fix All Multi-Service Appointments
 *
 * 1. Fix end_time for appointments with service_ids
 * 2. Migrate old appointments with "Dodatne usluge" in notes
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

echo "🔧 Ispravljanje svih multi-service termina\n";
echo str_repeat('=', 80) . "\n\n";

$dryRun = in_array('--dry-run', $argv);

if ($dryRun) {
    echo "⚠️  DRY RUN MODE - Nema promjena u bazi!\n\n";
}

$fixedEndTimes = 0;
$migratedOld = 0;
$errors = 0;

// PART 1: Fix end_time for appointments with service_ids
echo "📋 PART 1: Ispravljanje end_time za termine sa service_ids\n";
echo str_repeat('-', 80) . "\n\n";

$multiServiceAppointments = Appointment::whereNotNull('service_ids')
    ->where('date', '>=', Carbon::now()->subDays(90)->format('Y-m-d'))
    ->orderBy('date', 'asc')
    ->get();

echo "Pronađeno {$multiServiceAppointments->count()} termina sa service_ids\n\n";

foreach ($multiServiceAppointments as $appointment) {
    $serviceIds = $appointment->service_ids;

    if (!is_array($serviceIds) || empty($serviceIds)) {
        continue;
    }

    try {
        // Load all services
        $services = Service::whereIn('id', $serviceIds)->get();

        if ($services->count() !== count($serviceIds)) {
            echo "⚠️  Termin #{$appointment->id}: Neke usluge ne postoje u bazi\n";
            continue;
        }

        // Calculate total duration
        $totalDuration = 0;
        $serviceNames = [];
        foreach ($services as $service) {
            $totalDuration += $service->duration;
            $serviceNames[] = $service->name;
        }

        // Calculate expected end time
        $startTime = Carbon::parse($appointment->time);
        $expectedEndTime = $startTime->copy()->addMinutes($totalDuration);
        $actualEndTime = Carbon::parse($appointment->end_time);

        // Check if end_time needs fixing
        if ($expectedEndTime->format('H:i') !== $actualEndTime->format('H:i')) {
            echo "🔧 Termin #{$appointment->id}\n";
            echo "   Datum: {$appointment->date} {$appointment->time}\n";
            echo "   Klijent: {$appointment->client_name}\n";
            echo "   Usluge: " . implode(', ', $serviceNames) . "\n";
            echo "   Trajanje: {$totalDuration} min\n";
            echo "   Staro end_time: {$actualEndTime->format('H:i')}\n";
            echo "   Novo end_time: {$expectedEndTime->format('H:i')}\n";

            if (!$dryRun) {
                $appointment->end_time = $expectedEndTime->format('H:i:s');
                $appointment->save();
                echo "   ✅ Ispravljeno!\n";
            } else {
                echo "   [DRY RUN - nije sačuvano]\n";
            }
            echo "\n";

            $fixedEndTimes++;
        }
    } catch (\Exception $e) {
        echo "❌ Greška kod termina #{$appointment->id}: {$e->getMessage()}\n\n";
        $errors++;
    }
}

// PART 2: Migrate old appointments with "Dodatne usluge" in notes
echo "\n" . str_repeat('=', 80) . "\n";
echo "📋 PART 2: Migracija starih termina sa 'Dodatne usluge'\n";
echo str_repeat('-', 80) . "\n\n";

$oldAppointments = Appointment::where('notes', 'like', '%Dodatne usluge:%')
    ->whereNull('service_ids')
    ->where('date', '>=', Carbon::now()->subDays(90)->format('Y-m-d'))
    ->orderBy('date', 'asc')
    ->get();

echo "Pronađeno {$oldAppointments->count()} starih termina\n\n";

foreach ($oldAppointments as $appointment) {
    try {
        // Parse notes to extract additional services
        $notes = $appointment->notes;
        $mainService = $appointment->service;

        if (!$mainService) {
            echo "⚠️  Termin #{$appointment->id}: Nema glavnu uslugu\n\n";
            continue;
        }

        // Extract service names from notes
        preg_match('/Dodatne usluge:\s*(.+)$/i', $notes, $matches);
        if (!isset($matches[1])) {
            continue;
        }

        $additionalServiceNames = array_map('trim', explode(',', $matches[1]));

        // Find services by name
        $additionalServices = Service::where('salon_id', $appointment->salon_id)
            ->whereIn('name', $additionalServiceNames)
            ->get();

        if ($additionalServices->isEmpty()) {
            echo "⚠️  Termin #{$appointment->id}: Dodatne usluge nisu pronađene u bazi\n";
            echo "   Tražene: " . implode(', ', $additionalServiceNames) . "\n\n";
            continue;
        }

        // Build service_ids array (main + additional)
        $allServiceIds = [$mainService->id];
        $allServiceNames = [$mainService->name];
        $totalDuration = $mainService->duration;

        foreach ($additionalServices as $service) {
            $allServiceIds[] = $service->id;
            $allServiceNames[] = $service->name;
            $totalDuration += $service->duration;
        }

        // Calculate correct end_time
        $startTime = Carbon::parse($appointment->time);
        $correctEndTime = $startTime->copy()->addMinutes($totalDuration);

        echo "🔄 Termin #{$appointment->id}\n";
        echo "   Datum: {$appointment->date} {$appointment->time}\n";
        echo "   Klijent: {$appointment->client_name}\n";
        echo "   Glavna usluga: {$mainService->name}\n";
        echo "   Dodatne usluge: " . implode(', ', $additionalServiceNames) . "\n";
        echo "   Sve usluge: " . implode(', ', $allServiceNames) . "\n";
        echo "   Ukupno trajanje: {$totalDuration} min\n";
        echo "   Staro end_time: {$appointment->end_time}\n";
        echo "   Novo end_time: {$correctEndTime->format('H:i')}\n";

        if (!$dryRun) {
            $appointment->service_ids = $allServiceIds;
            $appointment->end_time = $correctEndTime->format('H:i:s');
            // Keep original notes for reference
            $appointment->save();
            echo "   ✅ Migrirano!\n";
        } else {
            echo "   [DRY RUN - nije sačuvano]\n";
        }
        echo "\n";

        $migratedOld++;
    } catch (\Exception $e) {
        echo "❌ Greška kod termina #{$appointment->id}: {$e->getMessage()}\n\n";
        $errors++;
    }
}

// Summary
echo str_repeat('=', 80) . "\n";
echo "📊 REZIME\n";
echo str_repeat('=', 80) . "\n\n";

if ($dryRun) {
    echo "⚠️  DRY RUN MODE - Ništa nije promenjeno u bazi\n\n";
}

echo "Rezultati:\n";
echo "  ✅ Ispravljeno end_time: {$fixedEndTimes} termina\n";
echo "  ✅ Migrirano starih termina: {$migratedOld} termina\n";
echo "  ❌ Greške: {$errors}\n";
echo "\n";

$total = $fixedEndTimes + $migratedOld;

if ($total > 0) {
    if ($dryRun) {
        echo "Za primenu promena, pokrenite bez --dry-run:\n";
        echo "  php backend/fix_all_multi_service_appointments.php\n";
    } else {
        echo "✅ Svi termini su uspešno ispravljeni!\n";
        echo "\n";
        echo "Šta je urađeno:\n";
        echo "  1. Ispravljeno end_time za termine sa više usluga\n";
        echo "  2. Migrirani stari termini sa 'Dodatne usluge' u napomenama\n";
        echo "  3. Svi termini sada imaju ispravno trajanje i service_ids\n";
        echo "\n";
        echo "Provera:\n";
        echo "  php backend/check_multi_service_end_times.php\n";
    }
} else {
    echo "✅ Nema termina za ispravljanje!\n";
}
