<?php

/**
 * Check Widget Deployment Status
 * Run this to see if frontend is properly deployed
 */

echo "\n";
echo "========================================\n";
echo "Widget Deployment Status Check\n";
echo "========================================\n\n";

// Frontend path
$frontendPath = '/var/www/vhosts/frizerino.com/frizerino.com';

echo "1. Checking frontend path...\n";
echo "----------------------------\n";
if (!is_dir($frontendPath)) {
    echo "❌ Frontend path does not exist: $frontendPath\n";
    exit(1);
}
echo "✅ Frontend path exists\n\n";

echo "2. Checking dist/ folder...\n";
echo "----------------------------\n";
$distPath = $frontendPath . '/dist';
if (!is_dir($distPath)) {
    echo "❌ dist/ folder does not exist!\n";
    echo "   This means frontend was never built.\n";
    echo "   Run: cd $frontendPath && npm run build\n";
    exit(1);
}
echo "✅ dist/ folder exists\n\n";

echo "3. Checking dist/assets/ files...\n";
echo "----------------------------------\n";
$assetsPath = $distPath . '/assets';
if (!is_dir($assetsPath)) {
    echo "❌ dist/assets/ folder does not exist!\n";
    exit(1);
}

$jsFiles = glob($assetsPath . '/*.js');
if (empty($jsFiles)) {
    echo "❌ No JavaScript files found in dist/assets/\n";
    exit(1);
}

echo "Found " . count($jsFiles) . " JavaScript files\n";
echo "Latest 5 files:\n";
usort($jsFiles, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

$now = time();
foreach (array_slice($jsFiles, 0, 5) as $file) {
    $mtime = filemtime($file);
    $age = $now - $mtime;
    $ageStr = '';

    if ($age < 60) {
        $ageStr = $age . ' seconds ago';
    } elseif ($age < 3600) {
        $ageStr = floor($age / 60) . ' minutes ago';
    } elseif ($age < 86400) {
        $ageStr = floor($age / 3600) . ' hours ago';
    } else {
        $ageStr = floor($age / 86400) . ' days ago';
    }

    $size = filesize($file);
    $sizeStr = $size < 1024 ? $size . ' B' : ($size < 1048576 ? round($size/1024, 1) . ' KB' : round($size/1048576, 1) . ' MB');

    echo "  " . basename($file) . " - " . $sizeStr . " - " . date('Y-m-d H:i:s', $mtime) . " ($ageStr)\n";
}
echo "\n";

// Check if files are recent (less than 1 hour old)
$newestFile = $jsFiles[0];
$newestTime = filemtime($newestFile);
$age = $now - $newestTime;

if ($age < 300) {
    echo "✅ Build is FRESH (less than 5 minutes old)\n";
} elseif ($age < 3600) {
    echo "⚠️  Build is recent but not fresh (" . floor($age/60) . " minutes old)\n";
} else {
    echo "❌ Build is OLD (" . floor($age/3600) . " hours old)\n";
    echo "   You need to rebuild: cd $frontendPath && npm run build\n";
}
echo "\n";

echo "4. Checking source code...\n";
echo "--------------------------\n";
$guestBookingModal = $frontendPath . '/src/components/Public/GuestBookingModal.tsx';
if (!file_exists($guestBookingModal)) {
    echo "❌ GuestBookingModal.tsx not found!\n";
    exit(1);
}

$content = file_get_contents($guestBookingModal);
if (strpos($content, 'totalDuration === 0') !== false) {
    echo "✅ Zero duration validation found in source code\n";
} else {
    echo "❌ Zero duration validation NOT found in source code!\n";
    echo "   This means the code was not committed or pulled.\n";
    echo "   Run: cd $frontendPath && git pull origin main\n";
}
echo "\n";

echo "5. Checking git status...\n";
echo "-------------------------\n";
$currentDir = getcwd();
chdir($frontendPath);
exec('git log -1 --oneline', $output, $returnCode);
if ($returnCode === 0 && !empty($output)) {
    echo "Latest commit: " . $output[0] . "\n";
} else {
    echo "⚠️  Could not get git status\n";
}
chdir($currentDir);
echo "\n";

echo "========================================\n";
echo "SUMMARY\n";
echo "========================================\n\n";

if ($age < 300 && strpos($content, 'totalDuration === 0') !== false) {
    echo "✅ Everything looks good!\n";
    echo "   - Source code has the fix\n";
    echo "   - Build is fresh\n";
    echo "\n";
    echo "If widget still doesn't work:\n";
    echo "1. Check widget URL is correct\n";
    echo "2. Check browser console (F12) for errors\n";
    echo "3. Check Network tab to see which files are loaded\n";
    echo "4. Try: https://frizerino.com/widget/[salon-slug]?key=[api-key]\n";
} else {
    echo "❌ Issues found!\n\n";
    echo "To fix:\n";
    echo "1. cd $frontendPath\n";
    echo "2. git pull origin main\n";
    echo "3. npm run build\n";
    echo "4. Run this script again to verify\n";
}
echo "\n";

