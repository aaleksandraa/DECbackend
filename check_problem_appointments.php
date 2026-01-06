<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Appointment;
use App\Models\Service;

$appointments = Appointment::whereIn('id', [14968, 14967])->get();

foreach ($appointments as $appointment) {
    echo "Termin #{$appointment->id}:\n";
    echo "  service_ids: " . json_encode($appointment->service_ids) . "\n";
    echo "  service_id: {$appointment->service_id}\n";

    if ($appointment->service_ids) {
        $services = Service::whereIn('id', $appointment->service_ids)->get();
        echo "  Pronađene usluge: {$services->count()} od " . count($appointment->service_ids) . "\n";
        foreach ($services as $service) {
            echo "    - {$service->name} ({$service->duration} min)\n";
        }

        $missing = array_diff($appointment->service_ids, $services->pluck('id')->toArray());
        if (!empty($missing)) {
            echo "  ⚠️  Nedostaju usluge sa ID: " . implode(', ', $missing) . "\n";
        }
    }
    echo "\n";
}
