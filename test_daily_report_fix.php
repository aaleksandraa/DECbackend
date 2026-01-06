<?php

/**
 * Test Daily Report Boolean Fix
 *
 * This script tests if the daily report command works correctly
 * after fixing the boolean comparison issue.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Salon;
use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "Daily Report Boolean Fix Test\n";
echo "========================================\n\n";

// Test 1: Check if boolean columns exist
echo "Test 1: Checking boolean columns...\n";
try {
    $result = DB::select("
        SELECT column_name, data_type
        FROM information_schema.columns
        WHERE table_name = 'salon_settings'
        AND column_name = 'daily_report_enabled'
    ");

    if (!empty($result)) {
        $column = $result[0];
        echo "✓ Column 'daily_report_enabled' exists\n";
        echo "  Type: {$column->data_type}\n";

        if ($column->data_type === 'boolean') {
            echo "  ✓ Correctly set as BOOLEAN\n";
        } else {
            echo "  ✗ WARNING: Still {$column->data_type}, should be BOOLEAN\n";
        }
    } else {
        echo "✗ Column 'daily_report_enabled' not found\n";
    }
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 2: Check for NULL values
echo "Test 2: Checking for NULL values...\n";
try {
    $nullCount = DB::table('salon_settings')
        ->whereNull('daily_report_enabled')
        ->count();

    if ($nullCount === 0) {
        echo "✓ No NULL values found\n";
    } else {
        echo "✗ WARNING: Found {$nullCount} NULL values\n";
        echo "  Run: UPDATE salon_settings SET daily_report_enabled = false WHERE daily_report_enabled IS NULL;\n";
    }
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: Test query with boolean comparison
echo "Test 3: Testing query with boolean comparison...\n";
try {
    $salons = Salon::whereHas('settings', function ($q) {
        $q->where('daily_report_enabled', true);
    })->with(['settings', 'owner'])->get();

    echo "✓ Query executed successfully\n";
    echo "  Found {$salons->count()} salon(s) with daily reports enabled\n";

    if ($salons->count() > 0) {
        echo "\n  Salons with daily reports enabled:\n";
        foreach ($salons as $salon) {
            $email = $salon->settings->report_email ?? $salon->owner->email ?? 'N/A';
            $enabled = $salon->settings->daily_report_enabled ? 'Yes' : 'No';
            echo "  - {$salon->name} (ID: {$salon->id})\n";
            echo "    Email: {$email}\n";
            echo "    Enabled: {$enabled}\n";
        }
    }
} catch (\Exception $e) {
    echo "✗ Query failed: " . $e->getMessage() . "\n";
    echo "\n";
    echo "This is the error we're trying to fix!\n";
    echo "Make sure you've deployed the fix.\n";
}
echo "\n";

// Test 4: Test with false comparison
echo "Test 4: Testing query with false comparison...\n";
try {
    $salons = Salon::whereHas('settings', function ($q) {
        $q->where('daily_report_enabled', false);
    })->count();

    echo "✓ Query executed successfully\n";
    echo "  Found {$salons} salon(s) with daily reports disabled\n";
} catch (\Exception $e) {
    echo "✗ Query failed: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Check salon_settings table structure
echo "Test 5: Checking salon_settings boolean columns...\n";
try {
    $columns = DB::select("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns
        WHERE table_name = 'salon_settings'
        AND data_type = 'boolean'
        ORDER BY column_name
    ");

    if (!empty($columns)) {
        echo "✓ Found " . count($columns) . " boolean column(s):\n";
        foreach ($columns as $col) {
            $nullable = $col->is_nullable === 'YES' ? 'NULL' : 'NOT NULL';
            $default = $col->column_default ?? 'none';
            echo "  - {$col->column_name}: {$col->data_type} {$nullable} (default: {$default})\n";
        }
    } else {
        echo "✗ No boolean columns found\n";
    }
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
echo "\n";

echo "========================================\n";
echo "Test completed!\n";
echo "========================================\n\n";

echo "Next steps:\n";
echo "1. If all tests passed, the fix is working correctly\n";
echo "2. Test the actual command: php artisan reports:send-daily --salon=1\n";
echo "3. Check logs: tail -f storage/logs/laravel.log\n";
echo "4. Monitor cron job execution at 19:00\n";
echo "\n";
