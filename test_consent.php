<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UserConsent;

try {
    echo "Testing UserConsent::recordConsent...\n";

    $consent = UserConsent::recordConsent(
        1, // user_id
        'test_consent', // consent_type
        true, // accepted
        '127.0.0.1', // ip_address
        'Test User Agent' // user_agent
    );

    echo "✅ Success! Consent ID: " . $consent->id . "\n";
    echo "Accepted value: " . ($consent->accepted ? 'true' : 'false') . "\n";

    // Clean up
    $consent->delete();
    echo "✅ Test consent deleted\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
