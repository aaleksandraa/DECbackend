<?php

/**
 * Test Broadcasting Setup
 *
 * This script tests if Pusher broadcasting is configured correctly.
 * Run: php test_broadcast.php
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Broadcast;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "========================================\n";
echo "Broadcasting Configuration Test\n";
echo "========================================\n\n";

// Check broadcast driver
$driver = config('broadcasting.default');
echo "1. Broadcast Driver: " . $driver . "\n";

if ($driver !== 'pusher') {
    echo "   ⚠️  Warning: Driver is not 'pusher'. Set BROADCAST_DRIVER=pusher in .env\n\n";
} else {
    echo "   ✅ Correct driver\n\n";
}

// Check Pusher configuration
echo "2. Pusher Configuration:\n";
$pusherConfig = config('broadcasting.connections.pusher');

$requiredKeys = ['key', 'secret', 'app_id', 'options'];
$allPresent = true;

foreach ($requiredKeys as $key) {
    $value = $pusherConfig[$key] ?? null;
    $status = $value ? '✅' : '❌';

    if ($key === 'options') {
        $cluster = $value['cluster'] ?? null;
        echo "   {$status} Cluster: " . ($cluster ?: 'NOT SET') . "\n";
        if (!$cluster) $allPresent = false;
    } else {
        $masked = $value ? (strlen($value) > 8 ? substr($value, 0, 8) . '...' : $value) : 'NOT SET';
        echo "   {$status} " . ucfirst($key) . ": " . $masked . "\n";
        if (!$value) $allPresent = false;
    }
}

echo "\n";

if (!$allPresent) {
    echo "❌ Pusher is not fully configured!\n\n";
    echo "Add these to your .env file:\n";
    echo "BROADCAST_DRIVER=pusher\n";
    echo "PUSHER_APP_ID=your_app_id\n";
    echo "PUSHER_APP_KEY=your_app_key\n";
    echo "PUSHER_APP_SECRET=your_app_secret\n";
    echo "PUSHER_APP_CLUSTER=eu\n\n";
    echo "Get free credentials at: https://pusher.com/\n\n";
    exit(1);
}

// Test Pusher connection
echo "3. Testing Pusher Connection:\n";

try {
    $pusher = new Pusher\Pusher(
        $pusherConfig['key'],
        $pusherConfig['secret'],
        $pusherConfig['app_id'],
        $pusherConfig['options']
    );

    // Try to trigger a test event
    $result = $pusher->trigger('test-channel', 'test-event', ['message' => 'Hello from Laravel!']);

    if ($result) {
        echo "   ✅ Successfully connected to Pusher!\n";
        echo "   ✅ Test event sent to 'test-channel'\n\n";

        echo "========================================\n";
        echo "✅ All Tests Passed!\n";
        echo "========================================\n\n";

        echo "Next steps:\n";
        echo "1. Configure frontend .env with VITE_PUSHER_APP_KEY\n";
        echo "2. Rebuild frontend: npm run build\n";
        echo "3. Test real-time notifications!\n\n";

        echo "Check Pusher Dashboard to see the test event:\n";
        echo "https://dashboard.pusher.com/\n\n";
    } else {
        echo "   ❌ Failed to send test event\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
    echo "Possible issues:\n";
    echo "- Invalid Pusher credentials\n";
    echo "- Network connectivity issues\n";
    echo "- Firewall blocking Pusher API\n\n";
    exit(1);
}
