<?php

/**
 * Check Multi-Service Appointments End Times
 *
 * Finds appointments with multiple services where end_time doesn't match
 * the total duration of all services.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;

echo "🔍 Provjera trajanja termina sa više usluga\n";
echo str_repeat('=', 80) . "\n\n";

// Find appointments with service_ids (multi-service)
$multiServiceAppointments = Appointment::whereNotNull('service_ids')
    ->where('date', '>=', Carbon::now()->subDays(30)->format('Y-m-d'))
    ->orderBy('date', 'desc')
    ->orderBy('time', 'desc')
    ->get();

echo "Pronađeno {$multiServiceAppointments->count()} termina sa više usluga (zadnjih 30 dana)\n\n";

$incorrectCount = 0;
$correctCount = 0;
$issues = [];

foreach ($multiServiceAppointments as $appointment) {
    $serviceIds = $appointment->service_ids;

    if (!is_array($serviceIds) || empty($serviceIds)) {
        continue;
    }

    // Load all services
    $services = Service::whereIn('id', $serviceIds)->get();

    // Calculate total duration
    $totalDuration = 0;
    $serviceNames = [];
    foreach ($services as $service) {
        $totalDuration += $service->duration;
        $serviceNames[] = $service->name . " ({$service->duration}min)";
    }

    // Calculate expected end time
    $startTime = Carbon::parse($appointment->time);
    $expectedEndTime = $startTime->copy()->addMinutes($totalDuration);
    $actualEndTime = Carbon::parse($appointment->end_time);

    // Check if end_time matches
    if ($expectedEndTime->format('H:i') !== $actualEndTime->format('H:i')) {
        $incorrectCount++;

        $issue = [
            'id' => $appointment->id,
            'date' => $appointment->date,
            'time' => $appointment->time,
            'client' => $appointment->client_name,
            'staff' => $appointment->staff->name ?? 'N/A',
            'services' => $serviceNames,
            'total_duration' => $totalDuration,
            'current_end_time' => $actualEndTime->format('H:i'),
            'expected_end_time' => $expectedEndTime->format('H:i'),
            'difference' => $actualEndTime->diffInMinutes($expectedEndTime, false),
        ];

        $issues[] = $issue;

        echo "❌ Termin #{$appointment->id}\n";
        echo "   Datum: {$appointment->date} {$appointment->time}\n";
        echo "   Klijent: {$appointment->client_name}\n";
        echo "   Frizer: " . ($appointment->staff->name ?? 'N/A') . "\n";
        echo "   Usluge: " . implode(', ', $serviceNames) . "\n";
        echo "   Ukupno trajanje: {$totalDuration} min\n";
        echo "   Trenutno end_time: {$actualEndTime->format('H:i')}\n";
        echo "   Očekivano end_time: {$expectedEndTime->format('H:i')}\n";
        echo "   Razlika: " . abs($issue['difference']) . " min\n";
        echo "\n";
    } else {
        $correctCount++;
    }
}

echo str_repeat('=', 80) . "\n";
echo "📊 Rezime:\n";
echo "   ✅ Ispravni termini: {$correctCount}\n";
echo "   ❌ Neispravni termini: {$incorrectCount}\n";
echo "\n";

if ($incorrectCount > 0) {
    echo "⚠️  Pronađeno {$incorrectCount} termina sa pogrešnim trajanjem!\n";
    echo "\n";
    echo "Možete ih ispraviti pokretanjem:\n";
    echo "   php backend/fix_multi_service_end_times.php\n";
} else {
    echo "✅ Svi termini imaju ispravno trajanje!\n";
}

// Also check appointments with notes containing "Dodatne usluge:"
echo "\n" . str_repeat('=', 80) . "\n";
echo "🔍 Provjera termina sa 'Dodatne usluge' u napomenama\n";
echo str_repeat('=', 80) . "\n\n";

$appointmentsWithNotes = Appointment::where('notes', 'like', '%Dodatne usluge:%')
    ->where('date', '>=', Carbon::now()->subDays(30)->format('Y-m-d'))
    ->whereNull('service_ids') // These should have service_ids but don't
    ->orderBy('date', 'desc')
    ->get();

if ($appointmentsWithNotes->count() > 0) {
    echo "⚠️  Pronađeno {$appointmentsWithNotes->count()} termina sa 'Dodatne usluge' ali bez service_ids!\n\n";

    foreach ($appointmentsWithNotes as $appointment) {
        echo "Termin #{$appointment->id}\n";
        echo "   Datum: {$appointment->date} {$appointment->time} - {$appointment->end_time}\n";
        echo "   Klijent: {$appointment->client_name}\n";
        echo "   Glavna usluga: " . ($appointment->service->name ?? 'N/A') . "\n";
        echo "   Napomene: {$appointment->notes}\n";
        echo "\n";
    }

    echo "⚠️  Ovi termini su kreirani starim sistemom i trebaju migraciju!\n";
} else {
    echo "✅ Nema starih termina sa 'Dodatne usluge' u napomenama\n";
}
