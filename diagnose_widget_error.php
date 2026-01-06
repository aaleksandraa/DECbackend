<?php

/**
 * Widget Error Diagnostic Script
 * Run: php diagnose_widget_error.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\WidgetSetting;

echo "========================================\n";
echo "Widget Error Diagnostic\n";
echo "========================================\n\n";

// 1. Check column types
echo "1. Checking column types:\n";
echo "----------------------------\n";

$columns = DB::select("
    SELECT
        table_name,
        column_name,
        data_type,
        column_default
    FROM information_schema.columns
    WHERE table_name IN ('widget_settings', 'services', 'staff', 'salons')
    AND column_name IN ('is_active', 'is_public', 'status')
    ORDER BY table_name, column_name
");

foreach ($columns as $col) {
    $status = $col->data_type === 'smallint' ? '❌ SMALLINT' : '✅ BOOLEAN';
    echo "{$col->table_name}.{$col->column_name}: {$col->data_type} {$status}\n";
}

echo "\n";

// 2. Check widget settings
echo "2. Checking widget settings:\n";
echo "----------------------------\n";

$widgets = DB::select("
    SELECT
        id,
        salon_id,
        api_key,
        is_active,
        pg_typeof(is_active) as is_active_type
    FROM widget_settings
    LIMIT 5
");

foreach ($widgets as $widget) {
    $keyPrefix = substr($widget->api_key, 0, 20);
    echo "Widget ID {$widget->id}: is_active={$widget->is_active} (type: {$widget->is_active_type})\n";
}

echo "\n";

// 3. Test query with 1
echo "3. Testing query with is_active = 1:\n";
echo "----------------------------\n";

try {
    $result = DB::select("
        SELECT COUNT(*) as count
        FROM widget_settings
        WHERE is_active = 1
    ");
    echo "✅ Query with '= 1' works: {$result[0]->count} rows\n";
} catch (\Exception $e) {
    echo "❌ Query with '= 1' failed: {$e->getMessage()}\n";
}

echo "\n";

// 4. Test query with true
echo "4. Testing query with is_active = true:\n";
echo "----------------------------\n";

try {
    $result = DB::select("
        SELECT COUNT(*) as count
        FROM widget_settings
        WHERE is_active = true
    ");
    echo "✅ Query with '= true' works: {$result[0]->count} rows\n";
} catch (\Exception $e) {
    echo "❌ Query with '= true' failed: {$e->getMessage()}\n";
}

echo "\n";

// 5. Test Laravel query with 1
echo "5. Testing Laravel query with where('is_active', 1):\n";
echo "----------------------------\n";

try {
    $count = WidgetSetting::where('is_active', 1)->count();
    echo "✅ Laravel query with 1 works: {$count} rows\n";
} catch (\Exception $e) {
    echo "❌ Laravel query with 1 failed: {$e->getMessage()}\n";
}

echo "\n";

// 6. Test Laravel query with true
echo "6. Testing Laravel query with where('is_active', true):\n";
echo "----------------------------\n";

try {
    $count = WidgetSetting::where('is_active', true)->count();
    echo "✅ Laravel query with true works: {$count} rows\n";
} catch (\Exception $e) {
    echo "❌ Laravel query with true failed: {$e->getMessage()}\n";
}

echo "\n";

// 7. Test specific API key
echo "7. Testing specific API key:\n";
echo "----------------------------\n";

$testKey = 'frzn_live_UgXYsmR4p43IPMkJmDHiBLRafVOaGaHz';

try {
    $widget = WidgetSetting::where('api_key', $testKey)->first();
    if ($widget) {
        echo "✅ Widget found:\n";
        echo "   ID: {$widget->id}\n";
        echo "   Salon ID: {$widget->salon_id}\n";
        echo "   is_active: {$widget->is_active}\n";
        echo "   is_active type: " . gettype($widget->is_active) . "\n";

        // Test with is_active check
        $activeWidget = WidgetSetting::where('api_key', $testKey)
            ->where('is_active', 1)
            ->first();

        if ($activeWidget) {
            echo "   ✅ Widget is active (query with 1 works)\n";
        } else {
            echo "   ❌ Widget not found with is_active = 1\n";
        }
    } else {
        echo "❌ Widget not found with this API key\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
}

echo "\n";

// 8. Check services and staff
echo "8. Checking services and staff columns:\n";
echo "----------------------------\n";

try {
    $servicesCount = DB::select("SELECT COUNT(*) as count FROM services WHERE is_active = 1");
    echo "✅ Services with is_active = 1: {$servicesCount[0]->count}\n";
} catch (\Exception $e) {
    echo "❌ Services query failed: {$e->getMessage()}\n";
}

try {
    $staffCount = DB::select("SELECT COUNT(*) as count FROM staff WHERE is_active = 1");
    echo "✅ Staff with is_active = 1: {$staffCount[0]->count}\n";
} catch (\Exception $e) {
    echo "❌ Staff query failed: {$e->getMessage()}\n";
}

echo "\n========================================\n";
echo "Diagnostic Complete\n";
echo "========================================\n";
