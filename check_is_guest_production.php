<?php

/**
 * Provjera is_guest tipa na produkciji
 *
 * Pokreni na produkciji:
 * cd /var/www/vhosts/frizerino.com/api.frizerino.com
 * php check_is_guest_production.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PROVJERA is_guest TIPA NA PRODUKCIJI ===\n\n";

// 1. Provjeri tip kolone u bazi
echo "1. TIP KOLONE u bazi:\n";
try {
    $columnInfo = DB::select("
        SELECT
            column_name,
            data_type,
            is_nullable,
            column_default
        FROM information_schema.columns
        WHERE table_name = 'appointments'
        AND column_name = 'is_guest'
    ");

    if (!empty($columnInfo)) {
        $info = $columnInfo[0];
        echo "   - Kolona: {$info->column_name}\n";
        echo "   - Tip: {$info->data_type}\n";
        echo "   - Nullable: {$info->is_nullable}\n";
        echo "   - Default: {$info->column_default}\n";
    } else {
        echo "   ❌ Kolona 'is_guest' ne postoji!\n";
    }
} catch (Exception $e) {
    echo "   ❌ Greška: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. Provjeri tip kolone u users tabeli
echo "2. TIP KOLONE u users tabeli:\n";
try {
    $columnInfo = DB::select("
        SELECT
            column_name,
            data_type,
            is_nullable,
            column_default
        FROM information_schema.columns
        WHERE table_name = 'users'
        AND column_name = 'is_guest'
    ");

    if (!empty($columnInfo)) {
        $info = $columnInfo[0];
        echo "   - Kolona: {$info->column_name}\n";
        echo "   - Tip: {$info->data_type}\n";
        echo "   - Nullable: {$info->is_nullable}\n";
        echo "   - Default: {$info->column_default}\n";
    } else {
        echo "   ❌ Kolona 'is_guest' ne postoji u users!\n";
    }
} catch (Exception $e) {
    echo "   ❌ Greška: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. Provjeri zadnji appointment sa is_guest
echo "3. ZADNJI APPOINTMENT sa is_guest:\n";
try {
    $lastAppointment = DB::table('appointments')
        ->whereNotNull('is_guest')
        ->orderBy('id', 'desc')
        ->first();

    if ($lastAppointment) {
        echo "   - ID: {$lastAppointment->id}\n";
        echo "   - is_guest vrijednost: " . var_export($lastAppointment->is_guest, true) . "\n";
        echo "   - is_guest tip: " . gettype($lastAppointment->is_guest) . "\n";
        echo "   - booking_source: {$lastAppointment->booking_source}\n";
        echo "   - created_at: {$lastAppointment->created_at}\n";
    } else {
        echo "   ⚠️  Nema appointmenta sa is_guest\n";
    }
} catch (Exception $e) {
    echo "   ❌ Greška: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. Provjeri zadnji widget appointment
echo "4. ZADNJI WIDGET APPOINTMENT:\n";
try {
    $lastWidget = DB::table('appointments')
        ->where('booking_source', 'widget')
        ->orderBy('id', 'desc')
        ->first();

    if ($lastWidget) {
        echo "   - ID: {$lastWidget->id}\n";
        echo "   - is_guest vrijednost: " . var_export($lastWidget->is_guest, true) . "\n";
        echo "   - is_guest tip: " . gettype($lastWidget->is_guest) . "\n";
        echo "   - status: {$lastWidget->status}\n";
        echo "   - created_at: {$lastWidget->created_at}\n";
    } else {
        echo "   ⚠️  Nema widget appointmenta\n";
    }
} catch (Exception $e) {
    echo "   ❌ Greška: " . $e->getMessage() . "\n";
}

echo "\n";

// 5. Test insert sa boolean
echo "5. TEST INSERT sa boolean (dry run):\n";
try {
    // Samo pripremi query bez izvršavanja
    $testData = [
        'salon_id' => 1,
        'staff_id' => 1,
        'date' => '2026-01-01',
        'time' => '10:00',
        'end_time' => '11:00',
        'status' => 'pending',
        'is_guest' => true, // Boolean
        'client_name' => 'Test',
        'booking_source' => 'test',
    ];

    echo "   ✅ Test data pripremljen:\n";
    echo "   - is_guest: " . var_export($testData['is_guest'], true) . " (" . gettype($testData['is_guest']) . ")\n";
    echo "   - Ovo bi trebalo da radi ako je kolona boolean\n";
} catch (Exception $e) {
    echo "   ❌ Greška: " . $e->getMessage() . "\n";
}

echo "\n";

// 6. Provjeri PHP verziju i ekstenzije
echo "6. PHP INFO:\n";
echo "   - PHP verzija: " . PHP_VERSION . "\n";
echo "   - PostgreSQL ekstenzija: " . (extension_loaded('pgsql') ? '✅ DA' : '❌ NE') . "\n";
echo "   - PDO PostgreSQL: " . (extension_loaded('pdo_pgsql') ? '✅ DA' : '❌ NE') . "\n";

echo "\n=== KRAJ PROVJERE ===\n";
