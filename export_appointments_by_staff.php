<?php

/**
 * Export Appointments by Staff - Professional Version
 *
 * Automatski detektuje strukturu staff tabele i eksportuje termine.
 * Podržava JSON i CSV format.
 *
 * Upotreba:
 *   php export_appointments_by_staff.php
 *   php export_appointments_by_staff.php --salon-id=1
 *   php export_appointments_by_staff.php --format=csv
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Parse command line arguments
$options = getopt('', ['salon-id:', 'format:', 'help']);

if (isset($options['help'])) {
    echo "Export Appointments by Staff\n\n";
    echo "Upotreba:\n";
    echo "  php export_appointments_by_staff.php [opcije]\n\n";
    echo "Opcije:\n";
    echo "  --salon-id=ID    Eksportuj samo termine za određeni salon\n";
    echo "  --format=FORMAT  Format eksporta: json (default) ili csv\n";
    echo "  --help           Prikaži ovu pomoć\n\n";
    exit(0);
}

$salonId = $options['salon-id'] ?? null;
$format = $options['format'] ?? 'json';

// Validate format
if (!in_array($format, ['json', 'csv'])) {
    echo "❌ Greška: Format mora biti 'json' ili 'csv'\n";
    exit(1);
}

echo "🚀 Eksport termina po radnicima\n";
echo str_repeat('=', 50) . "\n\n";

// Detect staff table columns
echo "🔍 Detekcija strukture staff tabele...\n";
$staffColumns = DB::select("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'staff'
    AND column_name IN ('id', 'name', 'email', 'phone', 'role', 'user_id', 'salon_id', 'deleted_at')
    ORDER BY ordinal_position
");

$availableColumns = array_column($staffColumns, 'column_name');
echo "   ✅ Pronađene kolone: " . implode(', ', $availableColumns) . "\n\n";

// Build dynamic select query
$selectColumns = ['staff.id', 'staff.name'];

// Add optional columns if they exist
if (in_array('phone', $availableColumns)) {
    $selectColumns[] = 'staff.phone';
}
if (in_array('role', $availableColumns)) {
    $selectColumns[] = 'staff.role';
}
if (in_array('user_id', $availableColumns)) {
    $selectColumns[] = 'staff.user_id';
}

$selectColumns[] = 'salons.name as salon_name';
$selectColumns[] = 'salons.slug as salon_slug';

// Create exports directory
$exportDir = __DIR__ . '/exports/appointments';
if (!file_exists($exportDir)) {
    mkdir($exportDir, 0755, true);
    echo "📁 Kreiran folder: $exportDir\n\n";
}

// Get all staff with their appointments
$query = DB::table('staff')
    ->select($selectColumns)
    ->join('salons', 'staff.salon_id', '=', 'salons.id')
    ->whereNull('staff.deleted_at');

if ($salonId) {
    $query->where('staff.salon_id', $salonId);
    echo "🏢 Filter: Samo salon ID $salonId\n\n";
}

$staffMembers = $query->get();

if ($staffMembers->isEmpty()) {
    echo "⚠️  Nema radnika za eksport.\n";
    exit(0);
}

echo "👥 Pronađeno radnika: " . count($staffMembers) . "\n\n";

$totalAppointments = 0;
$exportedFiles = [];

foreach ($staffMembers as $staff) {
    echo "📋 Eksportujem termine za: {$staff->name}\n";

    // Get user email and phone if user_id exists
    $userEmail = null;
    $userPhone = null;
    if (isset($staff->user_id) && $staff->user_id) {
        $user = DB::table('users')->where('id', $staff->user_id)->first();
        if ($user) {
            $userEmail = $user->email ?? null;
            $userPhone = $user->phone ?? null;
        }
    }

    // Get all appointments for this staff member
    $appointments = DB::table('appointments')
        ->select(
            'appointments.*',
            'salons.name as salon_name',
            'salons.slug as salon_slug',
            'salons.address as salon_address',
            'salons.city as salon_city',
            'salons.phone as salon_phone',
            'services.name as service_name',
            'services.duration as service_duration',
            'services.price as service_price'
        )
        ->join('salons', 'appointments.salon_id', '=', 'salons.id')
        ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
        ->where('appointments.staff_id', $staff->id)
        ->whereNull('appointments.deleted_at')
        ->orderBy('appointments.date', 'desc')
        ->orderBy('appointments.time', 'desc')
        ->get();

    if ($appointments->isEmpty()) {
        echo "   ⚠️  Nema termina\n\n";
        continue;
    }

    // Process appointments to include multi-service data
    $processedAppointments = [];
    foreach ($appointments as $appointment) {
        $appointmentData = (array) $appointment;

        // If service_ids exists, get all services
        if (!empty($appointment->service_ids)) {
            $serviceIds = json_decode($appointment->service_ids, true);
            if (is_array($serviceIds)) {
                $services = DB::table('services')
                    ->whereIn('id', $serviceIds)
                    ->get();

                $appointmentData['all_services'] = $services->map(function($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'duration' => $service->duration,
                        'price' => $service->price,
                    ];
                })->toArray();
            }
        }

        $processedAppointments[] = $appointmentData;
    }

    // Create safe filename
    $safeName = preg_replace('/[^a-z0-9_-]/i', '_', strtolower($staff->name));
    $safeName = preg_replace('/_+/', '_', $safeName);
    $timestamp = date('Y-m-d_H-i-s');

    if ($format === 'json') {
        // Export as JSON
        $filename = "{$safeName}_termini_{$timestamp}.json";
        $filepath = "$exportDir/$filename";

        $exportData = [
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $userEmail ?? (isset($staff->email) ? $staff->email : null),
                'phone' => $userPhone ?? (isset($staff->phone) ? $staff->phone : null),
                'role' => $staff->role ?? null,
                'user_id' => $staff->user_id ?? null,
                'salon_name' => $staff->salon_name,
                'salon_slug' => $staff->salon_slug,
            ],
            'export_date' => date('Y-m-d H:i:s'),
            'total_appointments' => count($processedAppointments),
            'appointments' => $processedAppointments,
        ];

        file_put_contents($filepath, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    } else {
        // Export as CSV
        $filename = "{$safeName}_termini_{$timestamp}.csv";
        $filepath = "$exportDir/$filename";

        $fp = fopen($filepath, 'w');

        // Add BOM for UTF-8
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

        // CSV Header
        fputcsv($fp, [
            'ID',
            'Datum',
            'Vrijeme',
            'Kraj',
            'Klijent',
            'Email',
            'Telefon',
            'Usluga',
            'Sve usluge',
            'Cijena',
            'Status',
            'Napomene',
            'Salon',
            'Kreiran',
        ], ';');

        // CSV Rows
        foreach ($processedAppointments as $apt) {
            $allServices = '';
            if (!empty($apt['all_services'])) {
                $allServices = implode(', ', array_column($apt['all_services'], 'name'));
            }

            fputcsv($fp, [
                $apt['id'],
                $apt['date'],
                $apt['time'],
                $apt['end_time'],
                $apt['client_name'],
                $apt['client_email'],
                $apt['client_phone'],
                $apt['service_name'] ?? '',
                $allServices,
                $apt['total_price'],
                $apt['status'],
                $apt['notes'] ?? '',
                $apt['salon_name'],
                $apt['created_at'],
            ], ';');
        }

        fclose($fp);
    }

    $filesize = filesize($filepath);
    $filesizeKB = round($filesize / 1024, 2);

    echo "   ✅ Eksportovano: " . count($processedAppointments) . " termina\n";
    echo "   📄 Fajl: $filename ($filesizeKB KB)\n\n";

    $totalAppointments += count($processedAppointments);
    $exportedFiles[] = $filename;
}

echo str_repeat('=', 50) . "\n";
echo "✅ Eksport završen!\n\n";
echo "📊 Statistika:\n";
echo "   • Radnika: " . count($staffMembers) . "\n";
echo "   • Ukupno termina: $totalAppointments\n";
echo "   • Eksportovanih fajlova: " . count($exportedFiles) . "\n";
echo "   • Format: " . strtoupper($format) . "\n";
echo "   • Lokacija: $exportDir\n\n";

if (!empty($exportedFiles)) {
    echo "📁 Eksportovani fajlovi:\n";
    foreach ($exportedFiles as $file) {
        echo "   • $file\n";
    }
    echo "\n";
}

echo "💡 Savjet: Možeš importovati ove fajlove u Excel, Google Sheets, ili drugi sistem.\n";
