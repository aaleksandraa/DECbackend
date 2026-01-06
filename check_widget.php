<?php
/**
 * Widget Debug Script
 * Run this on the server to check widget settings in database
 *
 * Usage: php check_widget.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WidgetSetting;
use Illuminate\Support\Facades\DB;

echo "=== Widget Settings Debug ===\n\n";

// Get all widget settings
$widgets = WidgetSetting::all();

echo "Total widgets: " . $widgets->count() . "\n\n";

foreach ($widgets as $widget) {
    echo "Widget ID: {$widget->id}\n";
    echo "Salon ID: {$widget->salon_id}\n";
    echo "API Key: " . substr($widget->api_key, 0, 25) . "...\n";
    echo "is_active value: " . var_export($widget->is_active, true) . "\n";
    echo "is_active type: " . gettype($widget->is_active) . "\n";
    echo "Allowed domains: " . json_encode($widget->allowed_domains) . "\n";
    echo "Last used: " . ($widget->last_used_at ?? 'Never') . "\n";
    echo "---\n";
}

// Raw query to see actual database values
echo "\n=== Raw Database Values ===\n";
$rawWidgets = DB::select("SELECT id, salon_id, api_key, is_active, is_active::text as is_active_text FROM widget_settings");

foreach ($rawWidgets as $raw) {
    echo "ID: {$raw->id}, is_active: " . var_export($raw->is_active, true) . ", is_active_text: {$raw->is_active_text}\n";
}

// Test specific API key
$testKey = 'frzn_live_fiLp9JQidKHcwo1vPsZLquw0nez2jNyh';
echo "\n=== Testing API Key ===\n";
echo "Looking for: {$testKey}\n";

$found = WidgetSetting::where('api_key', $testKey)->first();
if ($found) {
    echo "FOUND! Widget ID: {$found->id}\n";
    echo "is_active: " . var_export($found->is_active, true) . "\n";
} else {
    echo "NOT FOUND!\n";

    // Check if key exists with different case or whitespace
    $similar = DB::select("SELECT id, api_key FROM widget_settings WHERE api_key LIKE ?", ['%fiLp9JQidKHcwo1vPsZLquw0nez2jNyh%']);
    if (count($similar) > 0) {
        echo "Found similar keys:\n";
        foreach ($similar as $s) {
            echo "  ID: {$s->id}, Key: {$s->api_key}\n";
        }
    }
}

echo "\nDone!\n";
