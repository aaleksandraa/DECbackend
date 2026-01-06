<?php

/**
 * Test widget booking direktno
 *
 * Pokreni na produkciji:
 * cd /var/www/vhosts/frizerino.com/api.frizerino.com
 * php test_widget_booking.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;

echo "=== TEST WIDGET BOOKING ===\n\n";

// Test data
$testData = [
    'salon_id' => 1,
    'staff_id' => 1,
    'service_id' => null, // Za multi-service
    'service_ids' => [13, 14], // Dvije usluge
    'date' => '2026-01-03',
    'time' => '10:00',
    'end_time' => '11:00',
    'status' => 'confirmed',
    'client_name' => 'Test Widget',
    'client_email' => 'test@widget.com',
    'client_phone' => '123456789',
    'is_guest' => true, // BOOLEAN
    'guest_address' => null,
    'notes' => 'Test booking',
    'booking_source' => 'widget_test',
    'total_price' => 60,
    'payment_status' => 'pending',
];

echo "1. TEST DATA:\n";
foreach ($testData as $key => $value) {
    $type = gettype($value);
    $valueStr = is_array($value) ? json_encode($value) : var_export($value, true);
    echo "   - $key: $valueStr ($type)\n";
}

echo "\n2. POKUŠAJ INSERT:\n";

try {
    // Pokušaj kreirati appointment
    $appointment = Appointment::create($testData);

    echo "   ✅ USPJEŠNO KREIRANO!\n";
    echo "   - ID: {$appointment->id}\n";
    echo "   - is_guest: " . var_export($appointment->is_guest, true) . " (" . gettype($appointment->is_guest) . ")\n";
    echo "   - service_ids: " . json_encode($appointment->service_ids) . "\n";
    echo "   - created_at: {$appointment->created_at}\n";

    // Obriši test appointment
    echo "\n3. BRISANJE TEST APPOINTMENTA:\n";
    $appointment->delete();
    echo "   ✅ Test appointment obrisan (ID: {$appointment->id})\n";

} catch (\Exception $e) {
    echo "   ❌ GREŠKA!\n";
    echo "   - Message: " . $e->getMessage() . "\n";
    echo "   - File: " . $e->getFile() . "\n";
    echo "   - Line: " . $e->getLine() . "\n";

    // Provjeri da li je problem sa is_guest
    if (strpos($e->getMessage(), 'is_guest') !== false) {
        echo "\n   🔍 PROBLEM JE SA is_guest!\n";

        // Pokušaj sa različitim vrijednostima
        echo "\n4. TESTIRANJE RAZLIČITIH VRIJEDNOSTI:\n";

        $testValues = [
            'true (boolean)' => true,
            'false (boolean)' => false,
            '1 (integer)' => 1,
            '0 (integer)' => 0,
            '"true" (string)' => 'true',
            '"false" (string)' => 'false',
        ];

        foreach ($testValues as $label => $value) {
            try {
                $testData['is_guest'] = $value;
                $testData['booking_source'] = 'test_' . str_replace(' ', '_', $label);
                $testData['time'] = '10:' . str_pad(rand(10, 59), 2, '0', STR_PAD_LEFT);

                $test = Appointment::create($testData);
                echo "   ✅ $label - RADI (ID: {$test->id})\n";
                $test->delete();
            } catch (\Exception $e2) {
                echo "   ❌ $label - NE RADI: " . $e2->getMessage() . "\n";
            }
        }
    }
}

echo "\n=== KRAJ TESTA ===\n";
