<?php

/**
 * Debug script for search endpoint
 * Run: php debug_search.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== DEBUG SEARCH ENDPOINT ===\n\n";

try {
    // Test 1: Database connection
    echo "1. Testing database connection...\n";
    $pdo = DB::connection()->getPdo();
    echo "   ✓ Database connected\n\n";

    // Test 2: Check if salons table exists
    echo "2. Checking salons table...\n";
    $salonCount = DB::table('salons')->count();
    echo "   ✓ Salons table exists with {$salonCount} records\n\n";

    // Test 3: Check approved salons
    echo "3. Checking approved salons...\n";
    $approvedCount = DB::table('salons')->where('status', 'approved')->count();
    echo "   ✓ Found {$approvedCount} approved salons\n\n";

    // Test 4: Test basic query
    echo "4. Testing basic salon query...\n";
    $salons = DB::table('salons')
        ->where('status', 'approved')
        ->select('id', 'name', 'city', 'slug')
        ->limit(5)
        ->get();
    echo "   ✓ Query successful, found " . $salons->count() . " salons\n";
    foreach ($salons as $salon) {
        echo "     - {$salon->name} ({$salon->city})\n";
    }
    echo "\n";

    // Test 5: Test with relations
    echo "5. Testing query with relations...\n";
    $salon = \App\Models\Salon::approved()
        ->with(['images', 'services', 'owner'])
        ->withCount(['reviews', 'staff'])
        ->first();

    if ($salon) {
        echo "   ✓ Relations loaded successfully\n";
        echo "     - Images: " . $salon->images->count() . "\n";
        echo "     - Services: " . $salon->services->count() . "\n";
        echo "     - Reviews count: " . $salon->reviews_count . "\n";
        echo "     - Staff count: " . $salon->staff_count . "\n";
    } else {
        echo "   ⚠ No approved salons found\n";
    }
    echo "\n";

    // Test 6: Test search with parameters
    echo "6. Testing search with sort parameter...\n";
    $query = \App\Models\Salon::approved()
        ->with(['images', 'services', 'owner'])
        ->withCount(['reviews', 'staff'])
        ->orderBy('created_at', 'desc');

    $results = $query->limit(6)->get();
    echo "   ✓ Search with sort=newest successful, found " . $results->count() . " salons\n\n";

    // Test 7: Test search with audience filter
    echo "7. Testing search with audience filter...\n";
    $query = \App\Models\Salon::approved()
        ->with(['images', 'services', 'owner'])
        ->withCount(['reviews', 'staff'])
        ->whereJsonContains('target_audience->men', true);

    $results = $query->limit(6)->get();
    echo "   ✓ Search with audience=men successful, found " . $results->count() . " salons\n\n";

    // Test 8: Test cache
    echo "8. Testing cache...\n";
    try {
        Cache::put('test_key', 'test_value', 60);
        $value = Cache::get('test_key');
        if ($value === 'test_value') {
            echo "   ✓ Cache working\n";
        } else {
            echo "   ⚠ Cache not working properly\n";
        }
        Cache::forget('test_key');
    } catch (\Exception $e) {
        echo "   ✗ Cache error: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // Test 9: Test AppointmentService
    echo "9. Testing AppointmentService...\n";
    try {
        $service = app(\App\Services\AppointmentService::class);
        echo "   ✓ AppointmentService instantiated\n";
    } catch (\Exception $e) {
        echo "   ✗ AppointmentService error: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // Test 10: Simulate actual search request
    echo "10. Simulating actual search request...\n";
    try {
        $request = new \Illuminate\Http\Request([
            'sort' => 'newest',
            'per_page' => 6
        ]);

        $controller = new \App\Http\Controllers\Api\PublicController(
            app(\App\Services\AppointmentService::class),
            app(\App\Services\NotificationService::class)
        );

        $response = $controller->search($request);
        $data = json_decode($response->getContent(), true);

        echo "   ✓ Search request successful\n";
        echo "     - Status: " . $response->getStatusCode() . "\n";
        echo "     - Salons returned: " . count($data['salons'] ?? []) . "\n";
        echo "     - Total: " . ($data['meta']['total'] ?? 0) . "\n";
    } catch (\Exception $e) {
        echo "   ✗ Search request failed: " . $e->getMessage() . "\n";
        echo "     Stack trace:\n";
        echo "     " . $e->getTraceAsString() . "\n";
    }
    echo "\n";

    echo "=== ALL TESTS COMPLETED ===\n";

} catch (\Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
