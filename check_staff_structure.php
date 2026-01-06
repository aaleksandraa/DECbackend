<?php

/**
 * Check Staff Table Structure
 * Profesionalna provjera strukture staff tabele
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Provjera strukture staff tabele\n";
echo str_repeat('=', 50) . "\n\n";

// Get columns
$columns = DB::select("
    SELECT column_name, data_type, is_nullable, column_default
    FROM information_schema.columns
    WHERE table_name = 'staff'
    ORDER BY ordinal_position
");

echo "📋 Kolone u staff tabeli:\n\n";
foreach ($columns as $column) {
    $nullable = $column->is_nullable === 'YES' ? 'NULL' : 'NOT NULL';
    $default = $column->column_default ? " DEFAULT {$column->column_default}" : '';
    echo sprintf("  %-20s %-15s %-10s%s\n",
        $column->column_name,
        $column->data_type,
        $nullable,
        $default
    );
}

echo "\n";

// Count staff
$staffCount = DB::table('staff')->whereNull('deleted_at')->count();
echo "👥 Ukupno radnika: $staffCount\n\n";

// Get sample staff data
$staff = DB::table('staff')
    ->whereNull('deleted_at')
    ->first();

if ($staff) {
    echo "📊 Primjer podataka (prvi radnik):\n\n";
    foreach ($staff as $key => $value) {
        $displayValue = $value ?? 'NULL';
        if (is_string($displayValue) && strlen($displayValue) > 50) {
            $displayValue = substr($displayValue, 0, 50) . '...';
        }
        echo sprintf("  %-20s %s\n", $key . ':', $displayValue);
    }
    echo "\n";

    // If user_id exists, show user data
    if (isset($staff->user_id) && $staff->user_id) {
        $user = DB::table('users')->where('id', $staff->user_id)->first();
        if ($user) {
            echo "👤 Povezani user (user_id={$staff->user_id}):\n\n";
            echo sprintf("  %-20s %s\n", 'name:', $user->name);
            echo sprintf("  %-20s %s\n", 'email:', $user->email);
            echo sprintf("  %-20s %s\n", 'phone:', $user->phone ?? 'NULL');
            echo sprintf("  %-20s %s\n", 'role:', $user->role);
            echo "\n";
        }
    }
}

// Check relationships
echo "🔗 Provjera relacija:\n\n";

$staffWithUsers = DB::table('staff')
    ->whereNull('deleted_at')
    ->whereNotNull('user_id')
    ->count();

$staffWithoutUsers = DB::table('staff')
    ->whereNull('deleted_at')
    ->whereNull('user_id')
    ->count();

echo "  • Radnika sa user_id: $staffWithUsers\n";
echo "  • Radnika bez user_id: $staffWithoutUsers\n\n";

echo "✅ Provjera završena!\n";
echo "\n💡 Savjet: Eksport skripta će automatski detektovati dostupne kolone.\n";
