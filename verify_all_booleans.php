<?php

/**
 * Comprehensive Boolean Verification Script
 * Checks all boolean columns and potential issues
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n";
echo "========================================\n";
echo "BOOLEAN VERIFICATION - COMPREHENSIVE\n";
echo "========================================\n\n";

// 1. Check ALL boolean-like columns in database
echo "1. Checking ALL boolean columns in database:\n";
echo "----------------------------\n";

$columns = DB::select("
    SELECT
        table_name,
        column_name,
        data_type,
        column_default,
        is_nullable
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
    ORDER BY
        CASE
            WHEN data_type = 'smallint' THEN 1
            WHEN data_type = 'integer' THEN 2
            WHEN data_type = 'boolean' THEN 3
            ELSE 4
        END,
        table_name,
        column_name
");

$smallintCount = 0;
$booleanCount = 0;
$otherCount = 0;

echo "\n";
foreach ($columns as $col) {
    $status = '';
    if ($col->data_type === 'smallint') {
        $status = '❌ SMALLINT (needs migration)';
        $smallintCount++;
    } elseif ($col->data_type === 'boolean') {
        $status = '✅ BOOLEAN';
        $booleanCount++;
    } else {
        $status = '⚠️  ' . strtoupper($col->data_type);
        $otherCount++;
    }

    $nullable = $col->is_nullable === 'YES' ? 'NULL' : 'NOT NULL';
    $default = $col->column_default ?? 'no default';

    echo sprintf(
        "%-25s %-30s %-10s %-15s %s\n",
        $col->table_name,
        $col->column_name,
        $col->data_type,
        $nullable,
        $status
    );
}

echo "\n";
echo "Summary:\n";
echo "  ✅ BOOLEAN columns: $booleanCount\n";
echo "  ❌ SMALLINT columns: $smallintCount\n";
echo "  ⚠️  Other types: $otherCount\n";
echo "\n";

// 2. Test queries with different approaches
echo "2. Testing query approaches:\n";
echo "----------------------------\n";

$testTables = [
    'widget_settings' => 'is_active',
    'services' => 'is_active',
    'staff' => 'is_active',
    'users' => 'is_guest',
    'appointments' => 'is_guest',
];

foreach ($testTables as $table => $column) {
    echo "\nTable: $table.$column\n";

    // Check column type
    $colInfo = DB::select("
        SELECT data_type
        FROM information_schema.columns
        WHERE table_name = ? AND column_name = ?
    ", [$table, $column]);

    if (empty($colInfo)) {
        echo "  ⚠️  Column does not exist\n";
        continue;
    }

    $type = $colInfo[0]->data_type;
    echo "  Type: $type\n";

    // Test different query approaches
    $approaches = [
        'whereRaw true' => "SELECT COUNT(*) as count FROM $table WHERE $column = true",
        'whereRaw false' => "SELECT COUNT(*) as count FROM $table WHERE $column = false",
        'whereRaw 1' => "SELECT COUNT(*) as count FROM $table WHERE $column = 1",
        'whereRaw 0' => "SELECT COUNT(*) as count FROM $table WHERE $column = 0",
    ];

    foreach ($approaches as $name => $sql) {
        try {
            $result = DB::select($sql);
            echo "  ✅ $name: {$result[0]->count} rows\n";
        } catch (\Exception $e) {
            echo "  ❌ $name: " . substr($e->getMessage(), 0, 80) . "...\n";
        }
    }
}

echo "\n";

// 3. Check for NULL values in boolean columns
echo "3. Checking for NULL values in boolean columns:\n";
echo "----------------------------\n";

foreach ($columns as $col) {
    if ($col->is_nullable === 'YES') {
        try {
            $result = DB::select("
                SELECT COUNT(*) as count
                FROM {$col->table_name}
                WHERE {$col->column_name} IS NULL
            ");

            if ($result[0]->count > 0) {
                echo "⚠️  {$col->table_name}.{$col->column_name}: {$result[0]->count} NULL values\n";
            }
        } catch (\Exception $e) {
            // Table might not exist or other error
        }
    }
}

echo "\n";

// 4. Check Model casts
echo "4. Checking Model casts:\n";
echo "----------------------------\n";

$models = [
    'User' => \App\Models\User::class,
    'Appointment' => \App\Models\Appointment::class,
    'WidgetSetting' => \App\Models\WidgetSetting::class,
    'Staff' => \App\Models\Staff::class,
    'Service' => \App\Models\Service::class,
    'Location' => \App\Models\Location::class,
    'SalonSetting' => \App\Models\SalonSetting::class,
];

foreach ($models as $name => $class) {
    if (class_exists($class)) {
        try {
            $model = new $class;
            $casts = $model->getCasts();

            $booleanCasts = array_filter($casts, function($cast) {
                return $cast === 'boolean' || $cast === 'bool';
            });

            if (!empty($booleanCasts)) {
                echo "$name: " . implode(', ', array_keys($booleanCasts)) . "\n";
            }
        } catch (\Exception $e) {
            echo "$name: Error - {$e->getMessage()}\n";
        }
    }
}

echo "\n";

// 5. Search for problematic code patterns
echo "5. Checking for problematic code patterns:\n";
echo "----------------------------\n";

$problematicPatterns = [
    "where('is_active', 1)" => "Should use whereRaw('is_active = true') for BOOLEAN columns",
    "where('is_active', 0)" => "Should use whereRaw('is_active = false') for BOOLEAN columns",
    "where('is_guest', 1)" => "Should use whereRaw('is_guest = true') for BOOLEAN columns",
    "where('is_public', 1)" => "Should use whereRaw('is_public = true') for BOOLEAN columns",
];

echo "Note: Run this manually to search code:\n";
foreach ($problematicPatterns as $pattern => $suggestion) {
    $escapedPattern = str_replace("'", "\\'", $pattern);
    echo "  grep -r \"$escapedPattern\" app/ | wc -l\n";
}

echo "\n";

// 6. Final recommendations
echo "========================================\n";
echo "RECOMMENDATIONS\n";
echo "========================================\n\n";

if ($smallintCount > 0) {
    echo "❌ CRITICAL: $smallintCount SMALLINT columns found!\n";
    echo "   Action: Run boolean migration to convert to BOOLEAN\n";
    echo "   File: database/migrations/2024_12_28_000000_convert_smallint_to_boolean_safe.php\n";
    echo "   Command: php artisan migrate --force\n\n";
}

if ($booleanCount > 0) {
    echo "✅ GOOD: $booleanCount BOOLEAN columns found\n";
    echo "   Action: Ensure code uses whereRaw('column = true/false')\n";
    echo "   Reason: Laravel converts where('column', true) to WHERE column = 1\n";
    echo "           PostgreSQL doesn't allow boolean = integer comparison\n\n";
}

echo "Next steps:\n";
echo "1. If SMALLINT columns exist: Run migration\n";
echo "2. Search for where('is_*', 1) patterns in code\n";
echo "3. Replace with whereRaw('is_* = true')\n";
echo "4. Test all endpoints\n";
echo "5. Monitor logs for 'operator does not exist: boolean = integer' errors\n";

echo "\n========================================\n";
echo "VERIFICATION COMPLETE\n";
echo "========================================\n\n";
