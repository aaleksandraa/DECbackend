<?php

/**
 * Debug Guest Booking Slots Issue
 *
 * Problem: "Nema termina" se pojavljuje iako su servisi odabrani
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Salon;
use App\Models\Staff;
use App\Models\Service;
use App\Services\SalonService;
use Illuminate\Support\Facades\Log;

echo "🔍 Debug Guest Booking Slots Issue\n";
echo str_repeat("=", 60) . "\n\n";

// Test data (adjust based on your actual data)
$salonId = 1;
$staffId = 1; // Milena ili drugi staff
$serviceId = 1; // Farbanje kose ili drugi servis
$date = '08.01.2026'; // Sutra ili bilo koji datum

echo "Test Parameters:\n";
echo "  Salon ID: {$salonId}\n";
echo "  Staff ID: {$staffId}\n";
echo "  Service ID: {$serviceId}\n";
echo "  Date: {$date}\n\n";

// 1. Check if salon exists
$salon = Salon::find($salonId);
if (!$salon) {
    echo "❌ Salon not found!\n";
    exit(1);
}
echo "✅ Salon: {$salon->name}\n";

// 2. Check if staff exists
$staff = Staff::find($staffId);
if (!$staff) {
    echo "❌ Staff not found!\n";
    exit(1);
}
echo "✅ Staff: {$staff->name}\n";

// 3. Check if service exists
$service = Service::find($serviceId);
if (!$service) {
    echo "❌ Service not found!\n";
    exit(1);
}
echo "✅ Service: {$service->name} (duration: {$service->duration} min)\n\n";

// 4. Check working hours
$dayOfWeek = strtolower(date('l', strtotime(str_replace('.', '-', strrev($date)))));
echo "Day of week: {$dayOfWeek}\n";

$salonHours = $salon->working_hours[$dayOfWeek] ?? null;
echo "Salon hours: " . json_encode($salonHours) . "\n";

$staffHours = $staff->working_hours[$dayOfWeek] ?? null;
echo "Staff hours: " . json_encode($staffHours) . "\n\n";

// 5. Test API call simulation
echo "Simulating API call:\n";
echo "POST /public/available-slots-multi\n";
echo "Body:\n";
$requestData = [
    'salon_id' => $salonId,
    'date' => $date,
    'services' => [
        [
            'serviceId' => $serviceId,
            'staffId' => $staffId,
            'duration' => $service->duration
        ]
    ]
];
echo json_encode($requestData, JSON_PRETTY_PRINT) . "\n\n";

// 6. Call SalonService directly
try {
    $salonService = app(SalonService::class);

    echo "Calling getAvailableTimeSlotsForMultipleServices...\n";
    $slots = $salonService->getAvailableTimeSlotsForMultipleServices(
        $salon,
        $date,
        $requestData['services']
    );

    echo "\n✅ Slots returned: " . count($slots) . "\n";

    if (empty($slots)) {
        echo "❌ NO SLOTS AVAILABLE!\n\n";
        echo "Possible reasons:\n";
        echo "  1. Staff is on vacation\n";
        echo "  2. Salon is closed on this day\n";
        echo "  3. Staff is not working on this day\n";
        echo "  4. All slots are booked\n";
        echo "  5. Working hours are not set correctly\n";
    } else {
        echo "\nAvailable slots:\n";
        foreach (array_slice($slots, 0, 10) as $slot) {
            echo "  - {$slot}\n";
        }
        if (count($slots) > 10) {
            echo "  ... and " . (count($slots) - 10) . " more\n";
        }
    }

} catch (\Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "Debug complete\n";
