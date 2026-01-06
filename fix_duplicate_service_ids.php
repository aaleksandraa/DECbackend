<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Appointment;

echo "🔧 Ispravljanje duplikata u service_ids\n";
echo str_repeat('=', 60) . "\n\n";

$appointments = Appointment::whereNotNull('service_ids')->get();

$fixed = 0;

foreach ($appointments as $appointment) {
    $serviceIds = $appointment->service_ids;

    if (!is_array($serviceIds) || empty($serviceIds)) {
        continue;
    }

    // Remove duplicates
    $uniqueIds = array_values(array_unique($serviceIds));

    if (count($uniqueIds) !== count($serviceIds)) {
        echo "Termin #{$appointment->id}:\n";
        echo "  Staro: " . json_encode($serviceIds) . "\n";
        echo "  Novo: " . json_encode($uniqueIds) . "\n";

        $appointment->service_ids = $uniqueIds;
        $appointment->save();

        echo "  ✅ Ispravljeno!\n\n";
        $fixed++;
    }
}

echo str_repeat('=', 60) . "\n";
echo "📊 Ispravljeno {$fixed} termina sa duplikatima\n";
