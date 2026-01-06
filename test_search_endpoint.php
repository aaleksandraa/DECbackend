<?php

/**
 * Simple test endpoint to check what's causing 500 error
 * Access via: https://api.frizerino.com/test_search_endpoint.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Load Laravel
    require __DIR__.'/vendor/autoload.php';
    $app = require_once __DIR__.'/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $result = [
        'status' => 'testing',
        'tests' => []
    ];

    // Test 1: Database
    try {
        $pdo = DB::connection()->getPdo();
        $result['tests']['database'] = 'OK';
    } catch (\Exception $e) {
        $result['tests']['database'] = 'FAILED: ' . $e->getMessage();
    }

    // Test 2: Salons count
    try {
        $count = DB::table('salons')->where('status', 'approved')->count();
        $result['tests']['salons_count'] = $count;
    } catch (\Exception $e) {
        $result['tests']['salons_count'] = 'FAILED: ' . $e->getMessage();
    }

    // Test 3: Basic query
    try {
        $salons = \App\Models\Salon::approved()
            ->select('id', 'name', 'city')
            ->limit(3)
            ->get();
        $result['tests']['basic_query'] = 'OK - ' . $salons->count() . ' salons';
    } catch (\Exception $e) {
        $result['tests']['basic_query'] = 'FAILED: ' . $e->getMessage();
    }

    // Test 4: Query with relations
    try {
        $salons = \App\Models\Salon::approved()
            ->with(['images', 'services'])
            ->withCount(['reviews', 'staff'])
            ->limit(3)
            ->get();
        $result['tests']['query_with_relations'] = 'OK - ' . $salons->count() . ' salons';
    } catch (\Exception $e) {
        $result['tests']['query_with_relations'] = 'FAILED: ' . $e->getMessage();
    }

    // Test 5: Paginate
    try {
        $salons = \App\Models\Salon::approved()
            ->with(['images', 'services'])
            ->withCount(['reviews', 'staff'])
            ->paginate(6);
        $result['tests']['paginate'] = 'OK - ' . $salons->total() . ' total';
    } catch (\Exception $e) {
        $result['tests']['paginate'] = 'FAILED: ' . $e->getMessage();
    }

    // Test 6: Cache
    try {
        Cache::put('test', 'value', 60);
        $val = Cache::get('test');
        Cache::forget('test');
        $result['tests']['cache'] = $val === 'value' ? 'OK' : 'FAILED';
    } catch (\Exception $e) {
        $result['tests']['cache'] = 'FAILED: ' . $e->getMessage();
    }

    // Test 7: AppointmentService
    try {
        $service = app(\App\Services\AppointmentService::class);
        $result['tests']['appointment_service'] = 'OK';
    } catch (\Exception $e) {
        $result['tests']['appointment_service'] = 'FAILED: ' . $e->getMessage();
    }

    // Test 8: Full search simulation
    try {
        $query = \App\Models\Salon::approved()
            ->with([
                'images' => function ($q) {
                    $q->orderBy('is_primary', 'desc')->limit(3);
                },
                'services' => function ($q) {
                    $q->select('id', 'salon_id', 'name', 'category', 'price', 'duration');
                },
                'owner:id,name,email'
            ])
            ->withCount(['reviews', 'staff'])
            ->select('id', 'name', 'slug', 'city', 'city_slug', 'address', 'phone', 'description', 'latitude', 'longitude', 'average_rating', 'is_approved')
            ->orderBy('created_at', 'desc')
            ->paginate(6);

        $result['tests']['full_search'] = 'OK - ' . $salons->total() . ' total, ' . $salons->count() . ' on page';
    } catch (\Exception $e) {
        $result['tests']['full_search'] = 'FAILED: ' . $e->getMessage();
        $result['error_trace'] = $e->getTraceAsString();
    }

    $result['status'] = 'completed';
    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
