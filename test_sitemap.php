<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== SITEMAP TEST ===\n\n";

$baseUrl = config('app.frontend_url');
echo "Base URL: {$baseUrl}\n\n";

// Test sitemap URLs
$sitemaps = [
    '/sitemap.xml' => 'Main Sitemap Index',
    '/sitemap-static.xml' => 'Static Pages',
    '/sitemap-cities.xml' => 'Cities & Categories',
    '/sitemap-salons.xml' => 'Individual Salons',
    '/sitemap-staff.xml' => 'Staff Members',
    '/sitemap-services.xml' => 'Services',
];

echo "Testing Sitemap URLs:\n";
echo str_repeat('-', 60) . "\n\n";

foreach ($sitemaps as $path => $description) {
    echo "Testing: {$description}\n";
    echo "URL: {$baseUrl}{$path}\n";

    try {
        // Simulate HTTP request
        $controller = new \App\Http\Controllers\SitemapController();

        $method = match($path) {
            '/sitemap.xml' => 'index',
            '/sitemap-static.xml' => 'static',
            '/sitemap-cities.xml' => 'cities',
            '/sitemap-salons.xml' => 'salons',
            '/sitemap-staff.xml' => 'staff',
            '/sitemap-services.xml' => 'services',
        };

        $response = $controller->$method();
        $content = $response->getContent();

        // Count URLs
        $urlCount = substr_count($content, '<loc>');

        // Check if valid XML
        $xml = simplexml_load_string($content);

        if ($xml) {
            echo "✅ Valid XML\n";
            echo "📊 URLs: {$urlCount}\n";

            // Show first 3 URLs
            if ($urlCount > 0) {
                echo "Sample URLs:\n";
                preg_match_all('/<loc>(.*?)<\/loc>/', $content, $matches);
                $urls = array_slice($matches[1], 0, 3);
                foreach ($urls as $url) {
                    echo "  • " . htmlspecialchars_decode($url) . "\n";
                }
            }
        } else {
            echo "❌ Invalid XML\n";
        }

    } catch (\Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

// Statistics
echo str_repeat('=', 60) . "\n";
echo "STATISTICS\n";
echo str_repeat('=', 60) . "\n\n";

$stats = [
    'Total Salons' => \App\Models\Salon::where('status', 'approved')->count(),
    'Total Cities' => \App\Models\Salon::where('status', 'approved')->distinct('city_slug')->count('city_slug'),
    'Total Staff' => \App\Models\Staff::whereRaw('is_active = true')->count(),
    'Total Services' => \App\Models\Service::count(),
];

foreach ($stats as $label => $count) {
    echo sprintf("%-20s: %d\n", $label, $count);
}

echo "\n";

// Robots.txt test
echo str_repeat('=', 60) . "\n";
echo "ROBOTS.TXT TEST\n";
echo str_repeat('=', 60) . "\n\n";

try {
    $robotsController = new \App\Http\Controllers\RobotsController();
    $response = $robotsController->index();
    $content = $response->getContent();

    echo "✅ Robots.txt generated successfully\n\n";
    echo "Content Preview:\n";
    echo str_repeat('-', 60) . "\n";
    echo substr($content, 0, 500) . "...\n";
    echo str_repeat('-', 60) . "\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// SEO Service test
echo str_repeat('=', 60) . "\n";
echo "SEO SERVICE TEST\n";
echo str_repeat('=', 60) . "\n\n";

try {
    $seoService = new \App\Services\SeoService();

    // Test homepage meta
    echo "Testing Homepage Meta:\n";
    $homepageMeta = $seoService->generateHomepageMeta();
    echo "Title: " . $homepageMeta['title'] . "\n";
    echo "Description: " . substr($homepageMeta['description'], 0, 100) . "...\n";
    echo "✅ Homepage meta generated\n\n";

    // Test city meta
    echo "Testing City Meta (Sarajevo):\n";
    $cityMeta = $seoService->generateCityMeta('sarajevo', 'frizeri');
    echo "Title: " . $cityMeta['title'] . "\n";
    echo "Description: " . substr($cityMeta['description'], 0, 100) . "...\n";
    echo "✅ City meta generated\n\n";

    // Test salon meta (if any salon exists)
    $salon = \App\Models\Salon::where('status', 'approved')->with(['services', 'reviews'])->first();
    if ($salon) {
        echo "Testing Salon Meta ({$salon->name}):\n";
        $salonMeta = $seoService->generateSalonMeta($salon);
        echo "Title: " . $salonMeta['title'] . "\n";
        echo "Description: " . substr($salonMeta['description'], 0, 100) . "...\n";
        echo "✅ Salon meta generated\n";
    } else {
        echo "⚠️  No approved salons found for testing\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";
echo str_repeat('=', 60) . "\n";
echo "TEST COMPLETE\n";
echo str_repeat('=', 60) . "\n";
