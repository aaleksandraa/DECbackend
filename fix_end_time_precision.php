<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;

echo "=== FIX END_TIME PRECISION ===\n\n";

echo "Problem: end_time može biti 10:30:01 umjesto 10:30:00\n";
echo "Ovo uzrokuje da slot 10:30 izgleda zauzet jer:\n";
echo "  (10:30:00 < 10:30:01) = true\n\n";

// Find all appointments with end_time that has seconds
$appointments = Appointment::whereIn('status', ['pending', 'confirmed', 'in_progress'])
    ->orderBy('date')
    ->orderBy('time')
    ->get();

echo "Pronađeno {$appointments->count()} aktivnih termina\n\n";

$fixed = 0;
$errors = 0;

foreach ($appointments as $apt) {
    // Parse end_time
    $endTime = $apt->end_time;

    // Check if it has seconds or is not in HH:MM format
    if (!preg_match('/^\d{2}:\d{2}$/', $endTime)) {
        echo "Termin #{$apt->id} ({$apt->date} {$apt->time}):\n";
        echo "  Trenutni end_time: $endTime\n";

        // Calculate correct end_time
        $totalDuration = 0;

        if ($apt->service_ids && is_array($apt->service_ids) && count($apt->service_ids) > 0) {
            // Multi-service
            $services = Service::whereIn('id', $apt->service_ids)->get();
            $totalDuration = $services->sum('duration');
        } elseif ($apt->service_id) {
            // Single service
            $service = Service::find($apt->service_id);
            if ($service) {
                $totalDuration = $service->duration;
            }
        }

        if ($totalDuration > 0) {
            $correctEndTime = Carbon::parse($apt->time)->addMinutes($totalDuration)->format('H:i');
            echo "  Ispravan end_time: $correctEndTime (trajanje: {$totalDuration}min)\n";

            try {
                $apt->update(['end_time' => $correctEndTime]);
                echo "  ✅ Ispravljeno\n\n";
                $fixed++;
            } catch (\Exception $e) {
                echo "  ❌ Greška: {$e->getMessage()}\n\n";
                $errors++;
            }
        } else {
            echo "  ⚠️  Ne mogu izračunati trajanje (nema service_id ili service_ids)\n\n";
            $errors++;
        }
    }
}

echo "\n=== REZULTAT ===\n";
echo "Ispravljeno: $fixed\n";
echo "Greške: $errors\n";
echo "Ukupno: {$appointments->count()}\n\n";

if ($fixed > 0) {
    echo "✅ end_time je sada u formatu HH:MM bez sekundi\n";
    echo "Widget booking bi sada trebao raditi ispravno\n";
}

echo "\n=== KRAJ ===\n";
