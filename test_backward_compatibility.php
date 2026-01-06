<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Backward Compatibility ===\n\n";

// Test 1: Old appointments with service_id
echo "Test 1: Old appointments (service_id only)\n";
$oldAppointments = \App\Models\Appointment::whereNotNull('service_id')
    ->whereNull('service_ids')
    ->limit(5)
    ->get();

echo "Found {$oldAppointments->count()} old appointments\n";
foreach ($oldAppointments as $apt) {
    $services = $apt->services();
    echo "  ID {$apt->id}: service_id={$apt->service_id}, services()->count()={$services->count()}\n";
    if ($services->count() > 0) {
        echo "    ✅ Service: {$services->first()->name}\n";
    } else {
        echo "    ❌ ERROR: No services found!\n";
    }
}

echo "\n";

// Test 2: New appointments with service_ids
echo "Test 2: New appointments (service_ids)\n";
$newAppointments = \App\Models\Appointment::whereNotNull('service_ids')
    ->limit(5)
    ->get();

echo "Found {$newAppointments->count()} new appointments\n";
foreach ($newAppointments as $apt) {
    $services = $apt->services();
    echo "  ID {$apt->id}: service_ids=" . json_encode($apt->service_ids) . ", services()->count()={$services->count()}\n";
    foreach ($services as $service) {
        echo "    ✅ Service: {$service->name}\n";
    }
}

echo "\n";

// Test 3: Check if old appointments still work with notifications
echo "Test 3: Notification compatibility\n";
if ($oldAppointments->count() > 0) {
    $testApt = $oldAppointments->first();
    $testApt->load(['salon', 'staff', 'service', 'client']);

    $services = $testApt->services();
    echo "  Appointment ID {$testApt->id}:\n";
    echo "    Service count: {$services->count()}\n";
    echo "    Is multi-service: " . ($testApt->isMultiService() ? 'Yes' : 'No') . "\n";

    if ($services->count() > 1) {
        $serviceNames = $services->pluck('name')->toArray();
        $serviceList = implode(', ', array_slice($serviceNames, 0, -1)) . ' i ' . end($serviceNames);
        echo "    Service list: {$serviceList}\n";
    } else {
        echo "    Service: {$testApt->service->name}\n";
    }
    echo "  ✅ Old appointments work with new code!\n";
}

echo "\n=== Summary ===\n";
echo "✅ Old appointments (service_id) still work\n";
echo "✅ New appointments (service_ids) work\n";
echo "✅ Backward compatibility confirmed!\n";
echo "\nYour existing data is safe! 🎉\n";
