<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== GOOGLE ANALYTICS SETTINGS CHECK ===\n\n";

// Check all analytics settings
$settings = \App\Models\SystemSetting::where('key', 'LIKE', '%analytics%')->get();

if ($settings->isEmpty()) {
    echo "❌ NO ANALYTICS SETTINGS FOUND IN DATABASE!\n\n";
    echo "Creating default settings...\n";

    // Create default settings
    \App\Models\SystemSetting::create([
        'key' => 'google_analytics_id',
        'value' => '',
        'type' => 'string',
        'group' => 'analytics',
        'description' => 'Google Analytics Measurement ID (G-XXXXXXXXXX)'
    ]);

    \App\Models\SystemSetting::create([
        'key' => 'google_analytics_enabled',
        'value' => 'false',
        'type' => 'boolean',
        'group' => 'analytics',
        'description' => 'Enable Google Analytics tracking'
    ]);

    echo "✅ Default settings created!\n\n";

    $settings = \App\Models\SystemSetting::where('key', 'LIKE', '%analytics%')->get();
}

echo "Found " . $settings->count() . " analytics settings:\n\n";

foreach ($settings as $setting) {
    echo "Key: {$setting->key}\n";
    echo "Value: " . ($setting->value ?: '(empty)') . "\n";
    echo "Type: {$setting->type}\n";
    echo "Group: {$setting->group}\n";
    echo "Description: {$setting->description}\n";
    echo "---\n\n";
}

// Test the API endpoint
echo "=== TESTING API ENDPOINT ===\n\n";

$gaId = \App\Models\SystemSetting::where('key', 'google_analytics_id')->first();
$gaEnabled = \App\Models\SystemSetting::where('key', 'google_analytics_enabled')->first();

echo "API Response would be:\n";
echo json_encode([
    'google_analytics_id' => $gaId ? $gaId->value : null,
    'google_analytics_enabled' => $gaEnabled ? ($gaEnabled->value === 'true' || $gaEnabled->value === '1' || $gaEnabled->value === true) : false,
], JSON_PRETTY_PRINT);

echo "\n\n=== RECOMMENDATIONS ===\n\n";

if (!$gaId || !$gaId->value) {
    echo "⚠️  Google Analytics ID is not set\n";
    echo "   Go to Admin → Settings → Analytics tab\n";
    echo "   Enter your GA4 Measurement ID (format: G-XXXXXXXXXX)\n\n";
}

if (!$gaEnabled || $gaEnabled->value !== 'true') {
    echo "⚠️  Google Analytics is disabled\n";
    echo "   Go to Admin → Settings → Analytics tab\n";
    echo "   Enable the 'Omogući praćenje' checkbox\n\n";
}

if ($gaId && $gaId->value && $gaEnabled && $gaEnabled->value === 'true') {
    echo "✅ Google Analytics is properly configured!\n";
    echo "   ID: {$gaId->value}\n";
    echo "   Status: Enabled\n\n";

    // Validate ID format
    if (preg_match('/^G-[A-Z0-9]+$/i', $gaId->value)) {
        echo "✅ Valid GA4 Measurement ID format\n\n";
    } elseif (preg_match('/^UA-\d+-\d+$/i', $gaId->value)) {
        echo "⚠️  Universal Analytics format detected (UA-XXXXX-X)\n";
        echo "   Consider upgrading to GA4 (G-XXXXXXXXXX)\n\n";
    } else {
        echo "❌ Invalid ID format!\n";
        echo "   Expected: G-XXXXXXXXXX (GA4) or UA-XXXXX-X (Universal Analytics)\n\n";
    }
}

echo "=== END OF CHECK ===\n";
