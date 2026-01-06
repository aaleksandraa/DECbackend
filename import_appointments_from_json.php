<?php

/**
 * Import Appointments from JSON
 *
 * Importuje termine iz JSON fajla koji je eksportovan sa export_appointments_by_staff.php
 *
 * Upotreba:
 *   php import_appointments_from_json.php exports/appointments/ime_fajla.json
 *   php import_appointments_from_json.php exports/appointments/ime_fajla.json --dry-run
 *   php import_appointments_from_json.php exports/appointments/ime_fajla.json --skip-duplicates
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Parse command line arguments
$options = getopt('', ['dry-run', 'skip-duplicates', 'help']);
$args = array_slice($argv, 1);
$args = array_filter($args, function($arg) {
    return !str_starts_with($arg, '--');
});
$args = array_values($args);

if (isset($options['help']) || empty($args)) {
    echo "Import Appointments from JSON\n\n";
    echo "Upotreba:\n";
    echo "  php import_appointments_from_json.php <json_fajl> [opcije]\n\n";
    echo "Opcije:\n";
    echo "  --dry-run           Simuliraj import bez upisa u bazu\n";
    echo "  --skip-duplicates   Preskoči termine koji već postoje\n";
    echo "  --help              Prikaži ovu pomoć\n\n";
    echo "Primjer:\n";
    echo "  php import_appointments_from_json.php exports/appointments/milena_termini.json\n\n";
    exit(0);
}

$jsonFile = $args[0];
$dryRun = isset($options['dry-run']);
$skipDuplicates = isset($options['skip-duplicates']);

echo "🚀 Import termina iz JSON fajla\n";
echo str_repeat('=', 50) . "\n\n";

// Check if file exists
if (!file_exists($jsonFile)) {
    echo "❌ Greška: Fajl ne postoji: $jsonFile\n";
    exit(1);
}

// Read JSON file
$jsonContent = file_get_contents($jsonFile);
$data = json_decode($jsonContent, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ Greška: Neispravan JSON format\n";
    echo "   " . json_last_error_msg() . "\n";
    exit(1);
}

// Validate JSON structure
if (!isset($data['staff']) || !isset($data['appointments'])) {
    echo "❌ Greška: JSON fajl nema očekivanu strukturu\n";
    echo "   Očekivana struktura: { staff: {...}, appointments: [...] }\n";
    exit(1);
}

$staff = $data['staff'];
$appointments = $data['appointments'];

echo "📋 Informacije o fajlu:\n";
echo "   • Radnik: {$staff['name']}\n";
if (!empty($staff['email'])) {
    echo "   • Email: {$staff['email']}\n";
}
if (!empty($staff['user_id'])) {
    echo "   • User ID: {$staff['user_id']}\n";
}
echo "   • Salon: {$staff['salon_name']}\n";
echo "   • Termina u fajlu: " . count($appointments) . "\n";
echo "   • Datum eksporta: {$data['export_date']}\n\n";

if ($dryRun) {
    echo "🔍 DRY RUN MODE - Nema upisa u bazu\n\n";
}

// Check if staff exists in database
// Try to find by staff ID first, then by user email, then by name
$dbStaff = null;

if (!empty($staff['id'])) {
    $dbStaff = DB::table('staff')
        ->where('id', $staff['id'])
        ->whereNull('deleted_at')
        ->first();
}

if (!$dbStaff && !empty($staff['user_id'])) {
    $dbStaff = DB::table('staff')
        ->where('user_id', $staff['user_id'])
        ->whereNull('deleted_at')
        ->first();
}

if (!$dbStaff && !empty($staff['email'])) {
    // Try to find user by email, then find staff by user_id
    $user = DB::table('users')->where('email', $staff['email'])->first();
    if ($user) {
        $dbStaff = DB::table('staff')
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->first();
    }
}

if (!$dbStaff) {
    echo "❌ Greška: Radnik '{$staff['name']}' ne postoji u bazi\n";
    echo "💡 Savjet: Prvo kreiraj radnika u sistemu\n";
    exit(1);
}

echo "✅ Radnik pronađen u bazi (ID: {$dbStaff->id})\n\n";

// Import appointments
$imported = 0;
$skipped = 0;
$errors = 0;

echo "📥 Importujem termine...\n\n";

foreach ($appointments as $index => $appointment) {
    $num = $index + 1;

    // Check for duplicates
    if ($skipDuplicates) {
        $exists = DB::table('appointments')
            ->where('staff_id', $dbStaff->id)
            ->where('date', $appointment['date'])
            ->where('time', $appointment['time'])
            ->where('client_email', $appointment['client_email'])
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            echo "   ⏭️  [$num] Preskoči: {$appointment['date']} {$appointment['time']} - {$appointment['client_name']} (već postoji)\n";
            $skipped++;
            continue;
        }
    }

    try {
        if (!$dryRun) {
            // Prepare appointment data
            $appointmentData = [
                'client_id' => $appointment['client_id'] ?? null,
                'client_name' => $appointment['client_name'],
                'client_email' => $appointment['client_email'],
                'client_phone' => $appointment['client_phone'],
                'is_guest' => $appointment['is_guest'] ?? true,
                'guest_address' => $appointment['guest_address'] ?? null,
                'salon_id' => $dbStaff->salon_id,
                'staff_id' => $dbStaff->id,
                'service_id' => $appointment['service_id'] ?? null,
                'service_ids' => $appointment['service_ids'] ?? null,
                'date' => $appointment['date'],
                'time' => $appointment['time'],
                'end_time' => $appointment['end_time'],
                'status' => $appointment['status'],
                'notes' => $appointment['notes'] ?? null,
                'total_price' => $appointment['total_price'],
                'payment_status' => $appointment['payment_status'] ?? 'pending',
                'booking_source' => 'import',
                'created_at' => $appointment['created_at'],
                'updated_at' => $appointment['updated_at'],
            ];

            DB::table('appointments')->insert($appointmentData);
        }

        echo "   ✅ [$num] Importovan: {$appointment['date']} {$appointment['time']} - {$appointment['client_name']}\n";
        $imported++;

    } catch (\Exception $e) {
        echo "   ❌ [$num] Greška: {$appointment['date']} {$appointment['time']} - {$e->getMessage()}\n";
        $errors++;
    }
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "✅ Import završen!\n\n";
echo "📊 Statistika:\n";
echo "   • Ukupno termina: " . count($appointments) . "\n";
echo "   • Importovano: $imported\n";
echo "   • Preskočeno: $skipped\n";
echo "   • Greške: $errors\n\n";

if ($dryRun) {
    echo "💡 Ovo je bio DRY RUN - nema izmjena u bazi.\n";
    echo "   Pokreni bez --dry-run opcije za stvarni import.\n\n";
}

if ($errors > 0) {
    echo "⚠️  Bilo je grešaka tokom importa. Provjeri log iznad.\n\n";
    exit(1);
}
