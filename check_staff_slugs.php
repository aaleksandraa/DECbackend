<?php

/**
 * Quick script to check staff slug status
 * Run: php check_staff_slugs.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Staff;

echo "=== Staff Slug Status Check ===\n\n";

$totalStaff = Staff::count();
$staffWithSlugs = Staff::whereNotNull('slug')->where('slug', '!=', '')->count();
$staffWithoutSlugs = Staff::whereNull('slug')->orWhere('slug', '')->count();

echo "Total Staff: {$totalStaff}\n";
echo "Staff with slugs: {$staffWithSlugs}\n";
echo "Staff without slugs: {$staffWithoutSlugs}\n\n";

if ($staffWithoutSlugs > 0) {
    echo "❌ Staff members without slugs:\n";
    echo "-----------------------------------\n";

    $staff = Staff::whereNull('slug')->orWhere('slug', '')->get();
    foreach ($staff as $member) {
        $salon = $member->salon ? $member->salon->name : 'N/A';
        echo "ID: {$member->id} | Name: {$member->name} | Salon: {$salon} | Active: " . ($member->is_active ? 'Yes' : 'No') . "\n";
    }

    echo "\n";
    echo "💡 To fix this, run: php artisan staff:generate-slugs\n";
} else {
    echo "✅ All staff members have slugs!\n\n";

    echo "Sample staff with slugs:\n";
    echo "-----------------------------------\n";
    $staff = Staff::whereNotNull('slug')->limit(5)->get();
    foreach ($staff as $member) {
        $salon = $member->salon ? $member->salon->name : 'N/A';
        echo "Name: {$member->name} | Slug: {$member->slug} | Salon: {$salon}\n";
    }
}

echo "\n";

// Check is_public and is_active status
echo "=== Staff Visibility Status ===\n\n";

$publicStaff = Staff::whereRaw('is_public = true')->count();
$activeStaff = Staff::whereRaw('is_active = true')->count();
$publicAndActive = Staff::whereRaw('is_public = true')->whereRaw('is_active = true')->count();

echo "Public staff: {$publicStaff}\n";
echo "Active staff: {$activeStaff}\n";
echo "Public AND Active: {$publicAndActive}\n\n";

if ($publicAndActive < $totalStaff) {
    echo "⚠️  Some staff are not public or not active:\n";
    echo "-----------------------------------\n";

    $hiddenStaff = Staff::where(function($query) {
        $query->whereRaw('is_public = false')
              ->orWhereRaw('is_active = false');
    })->get();

    foreach ($hiddenStaff as $member) {
        $salon = $member->salon ? $member->salon->name : 'N/A';
        $status = [];
        if (!$member->is_public) $status[] = 'Not Public';
        if (!$member->is_active) $status[] = 'Not Active';
        echo "Name: {$member->name} | Salon: {$salon} | Status: " . implode(', ', $status) . "\n";
    }
} else {
    echo "✅ All staff are public and active!\n";
}

echo "\n=== Check Complete ===\n";
