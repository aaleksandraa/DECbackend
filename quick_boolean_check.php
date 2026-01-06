<?php

/**
 * Quick Boolean Status Check
 * Run: php quick_boolean_check.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n";
echo "========================================\n";
echo "QUICK BOOLEAN STATUS CHECK\n";
echo "========================================\n\n";

// 1. Count column types
echo "1. Column Type Summary:\n";
echo "----------------------------\n";

$columns = DB::select("
    SELECT
        data_type,
        COUNT(*) as count
    FROM information_schema.columns
    WHERE table_schema = 'public'
    AND (
        column_name LIKE '%is_%'
        OR column_name LIKE '%has_%'
        OR column_name LIKE '%can_%'
        OR column_name LIKE '%accepted%'
        OR column_name LIKE '%enabled%'
        OR column_name LIKE '%active%'
        OR column_name LIKE '%public%'
        OR column_name LIKE '%verified%'
        OR column_name LIKE '%featured%'
        OR column_name LIKE '%primary%'
        OR column_name LIKE '%read%'
        OR column_name = 'auto_confirm'
        OR column_name = 'accepts_bookings'
    )
    AND table_name NOT LIKE 'pg_%'
    AND table_name NOT LIKE 'sql_%'
    GROUP BY data_type
    ORDER BY data_type
");

foreach ($columns as $col) {
    $icon = $col->data_type === 'boolean' ? '✅' : ($col->data_type === 'smallint' ? '⚠️ ' : '❓');
    echo "$icon " . strtoupper($col->data_type) . ": {$col->count} columns\n";
}

echo "\n";

// 2. List SMALLINT columns specifically
echo "2. SMALLINT Columns (need special handling):\n";
echo "----------------------------\n";

$smallintCols = DB::select("
    SELECT
        table_name,
        column_name
    FROM information_schema.columns
    WHERE table_schema = 'public'
    AND data_type = 'smallint'
    AND (
        column_name LIKE '%is_%'
        OR column_name LIKE '%has_%'
        OR column_name = 'auto_confirm'
    )
    ORDER BY table_name, column_name
");

if (empty($smallintCols)) {
    echo "✅ No SMALLINT boolean columns found!\n";
} else {
    foreach ($smallintCols as $col) {
        echo "⚠️  {$col->table_name}.{$col->column_name}\n";
    }
}

echo "\n";

// 3. Test widget query
echo "3. Widget Query Test:\n";
echo "----------------------------\n";

try {
    $activeWidgets = DB::select("
        SELECT COUNT(*) as count
        FROM widget_settings
        WHERE is_active = true
    ");
    echo "✅ Widget query works: {$activeWidgets[0]->count} active widgets\n";
} catch (\Exception $e) {
    echo "❌ Widget query failed: " . substr($e->getMessage(), 0, 100) . "\n";
}

echo "\n";

// 4. Test appointment query
echo "4. Appointment Query Test:\n";
echo "----------------------------\n";

try {
    $guestAppointments = DB::select("
        SELECT COUNT(*) as count
        FROM appointments
        WHERE is_guest = 1
    ");
    echo "✅ Appointment query works: {$guestAppointments[0]->count} guest appointments\n";
} catch (\Exception $e) {
    echo "❌ Appointment query failed: " . substr($e->getMessage(), 0, 100) . "\n";
}

echo "\n";

// 5. Check recent errors in logs
echo "5. Recent Log Check:\n";
echo "----------------------------\n";

$logFile = __DIR__ . '/storage/logs/laravel.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $recentLog = substr($logContent, -5000); // Last 5KB

    $booleanErrors = substr_count($recentLog, 'boolean = integer');
    $widgetErrors = substr_count($recentLog, 'Widget');

    if ($booleanErrors > 0) {
        echo "❌ Found $booleanErrors boolean type errors in recent logs\n";
    } else {
        echo "✅ No boolean type errors in recent logs\n";
    }

    if ($widgetErrors > 0) {
        echo "⚠️  Found $widgetErrors widget-related log entries\n";
    } else {
        echo "✅ No widget errors in recent logs\n";
    }
} else {
    echo "⚠️  Log file not found\n";
}

echo "\n";

// 6. Final status
echo "========================================\n";
echo "STATUS SUMMARY\n";
echo "========================================\n\n";

$booleanCount = 0;
$smallintCount = 0;

foreach ($columns as $col) {
    if ($col->data_type === 'boolean') {
        $booleanCount = $col->count;
    } elseif ($col->data_type === 'smallint') {
        $smallintCount = $col->count;
    }
}

if ($booleanCount > 0 && $smallintCount > 0) {
    echo "✅ Mixed state detected (expected):\n";
    echo "   - $booleanCount BOOLEAN columns\n";
    echo "   - $smallintCount SMALLINT columns\n";
    echo "   - This is INTENTIONAL and SAFE\n";
    echo "   - Laravel's boolean cast handles both\n";
} elseif ($booleanCount > 0 && $smallintCount === 0) {
    echo "✅ All columns migrated to BOOLEAN\n";
} elseif ($booleanCount === 0 && $smallintCount > 0) {
    echo "⚠️  All columns are SMALLINT (old state)\n";
    echo "   Consider running migration\n";
}

echo "\n";
echo "Next steps:\n";
echo "1. If widget works: ✅ Everything is fine\n";
echo "2. If widget fails: Check logs with 'tail -100 storage/logs/laravel.log'\n";
echo "3. For detailed analysis: Run 'php verify_all_booleans.php'\n";

echo "\n========================================\n";
echo "CHECK COMPLETE\n";
echo "========================================\n\n";
