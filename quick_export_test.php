<?php

/**
 * Quick Export Test - Professional Version
 * Brzi test eksporta sa automatskom detekcijom strukture
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Brzi test eksporta\n";
echo str_repeat('=', 50) . "\n\n";

// Detect staff table columns
echo "🔍 Detekcija strukture...\n";
$staffColumns = DB::select("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'staff'
    AND column_name IN ('id', 'name', 'email', 'phone', 'role', 'user_id', 'salon_id')
");

$availableColumns = array_column($staffColumns, 'column_name');
echo "   ✅ Dostupne kolone: " . implode(', ', $availableColumns) . "\n\n";

// Build dynamic select
$selectColumns = ['staff.id', 'staff.name'];
if (in_array('phone', $availableColumns)) $selectColumns[] = 'staff.phone';
if (in_array('role', $availableColumns)) $selectColumns[] = 'staff.role';
if (in_array('user_id', $availableColumns)) $selectColumns[] = 'staff.user_id';
$selectColumns[] = 'salons.name as salon_name';

// Get first staff member
$staff = DB::table('staff')
    ->select($selectColumns)
    ->join('salons', 'staff.salon_id', '=', 'salons.id')
    ->whereNull('staff.deleted_at')
    ->first();

if (!$staff) {
    echo "❌ Nema radnika u bazi\n";
    exit(1);
}

echo "👤 Radnik: {$staff->name}\n";
echo "   • ID: {$staff->id}\n";
if (isset($staff->phone)) echo "   • Telefon: {$staff->phone}\n";
if (isset($staff->role)) echo "   • Uloga: {$staff->role}\n";
if (isset($staff->user_id)) echo "   • User ID: {$staff->user_id}\n";
echo "   • Salon: {$staff->salon_name}\n";

// Get user email if exists
if (isset($staff->user_id) && $staff->user_id) {
    $user = DB::table('users')->where('id', $staff->user_id)->first();
    if ($user) {
        echo "   • Email (iz users): {$user->email}\n";
        if ($user->phone) echo "   • Telefon (iz users): {$user->phone}\n";
    }
}

// Count appointments
$appointmentCount = DB::table('appointments')
    ->where('staff_id', $staff->id)
    ->whereNull('deleted_at')
    ->count();

echo "   • Termina: $appointmentCount\n\n";

echo "✅ Struktura je OK!\n";
echo "\n💡 Možeš pokrenuti puni eksport:\n";
echo "   php export_appointments_by_staff.php\n";
echo "   php export_appointments_by_staff.php --format=csv\n";
