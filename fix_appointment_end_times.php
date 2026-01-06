<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Appointment;
use Carbon\Carbon;

echo "=== POPRAVKA END_TIME ZA SVE TERMINE ===\n\n";

$appointments = Appointment::with('service')->get();

$fixed = 0;
$errors = 0;
$alreadyCorrect = 0;

foreach ($appointments as $apt) {
    if (!$apt->service) {
        echo "⚠️  Termin ID {$apt->id} nema uslugu - preskačem\n";
        $errors++;
        continue;
    }

    $start = Carbon::parse($apt->date . ' ' . $apt->time);
    $correctEndTime = $start->copy()->addMinutes($apt->service->duration)->format('H:i:s');

    if ($apt->end_time !== $correctEndTime) {
        echo "Popravljam termin ID {$apt->id}:\n";
        echo "  Datum: {$apt->date}\n";
        echo "  Klijent: {$apt->client_name}\n";
        echo "  Usluga: {$apt->service->name} ({$apt->service->duration} min)\n";
        echo "  Staro: {$apt->time} - {$apt->end_time}\n";
        echo "  Novo: {$apt->time} - {$correctEndTime}\n";
        echo "\n";

        $apt->update(['end_time' => $correctEndTime]);
        $fixed++;
    } else {
        $alreadyCorrect++;
    }
}

echo "\n=== REZULTATI ===\n";
echo "✅ Popravljeno: {$fixed} termina\n";
echo "✓  Već tačno: {$alreadyCorrect} termina\n";
if ($errors > 0) {
    echo "⚠️  Greške: {$errors} termina\n";
}
echo "📊 Ukupno: " . ($fixed + $alreadyCorrect + $errors) . " termina\n";

echo "\n=== GOTOVO ===\n";
