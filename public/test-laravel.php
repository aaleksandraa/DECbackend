<?php

echo "=== Laravel Bootstrap Test ===\n\n";

// Test 1: Check paths
echo "1. Checking paths...\n";
echo "   Current dir: " . __DIR__ . "\n";
echo "   Parent dir: " . dirname(__DIR__) . "\n";
echo "   Vendor exists: " . (file_exists(__DIR__ . '/../vendor/autoload.php') ? '✅' : '❌') . "\n";
echo "   Bootstrap exists: " . (file_exists(__DIR__ . '/../bootstrap/app.php') ? '✅' : '❌') . "\n";
echo "   .env exists: " . (file_exists(__DIR__ . '/../.env') ? '✅' : '❌') . "\n\n";

// Test 2: Load autoloader
echo "2. Loading Composer autoloader...\n";
try {
    require __DIR__ . '/../vendor/autoload.php';
    echo "   ✅ Autoloader loaded\n\n";
} catch (Exception $e) {
    echo "   ❌ Autoloader failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 3: Bootstrap Laravel
echo "3. Bootstrapping Laravel...\n";
try {
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    echo "   ✅ Laravel app created\n";
    echo "   App class: " . get_class($app) . "\n\n";
} catch (Exception $e) {
    echo "   ❌ Laravel bootstrap failed: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n\n";
    exit(1);
}

// Test 4: Boot Laravel
echo "4. Booting Laravel kernel...\n";
try {
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    echo "   ✅ Kernel created\n";
    echo "   Kernel class: " . get_class($kernel) . "\n\n";
} catch (Exception $e) {
    echo "   ❌ Kernel creation failed: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n\n";
    exit(1);
}

// Test 5: Handle a test request
echo "5. Testing HTTP request handling...\n";
try {
    $request = Illuminate\Http\Request::create('/api/v1/health', 'GET');
    $response = $kernel->handle($request);
    echo "   ✅ Request handled\n";
    echo "   Status: " . $response->getStatusCode() . "\n";
    echo "   Content: " . substr($response->getContent(), 0, 100) . "\n\n";
} catch (Exception $e) {
    echo "   ❌ Request handling failed: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n\n";
    exit(1);
}

// Test 6: Check environment
echo "6. Checking Laravel environment...\n";
try {
    echo "   APP_ENV: " . env('APP_ENV', 'not set') . "\n";
    echo "   APP_DEBUG: " . (env('APP_DEBUG') ? 'true' : 'false') . "\n";
    echo "   APP_URL: " . env('APP_URL', 'not set') . "\n";
    echo "   DB_CONNECTION: " . env('DB_CONNECTION', 'not set') . "\n\n";
} catch (Exception $e) {
    echo "   ❌ Environment check failed: " . $e->getMessage() . "\n\n";
}

echo "=== All tests passed! ✅ ===\n";
echo "\nLaravel is working correctly.\n";
echo "The problem must be in Passenger configuration.\n";
