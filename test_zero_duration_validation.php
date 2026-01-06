<?php

/**
 * Test Zero Duration Validation in Source Code
 * This checks if the validation code exists in the source files
 */

echo "\n";
echo "========================================\n";
echo "Zero Duration Validation Test\n";
echo "========================================\n\n";

$frontendPath = '/var/www/vhosts/frizerino.com/frizerino.com';

echo "Checking source files...\n";
echo "------------------------\n\n";

// Files to check
$filesToCheck = [
    'src/components/Public/GuestBookingModal.tsx' => [
        'totalDuration === 0' => 'Zero duration check in canProceed()',
        'Ne možete rezervisati ovu uslugu samostalno' => 'Error message',
        'totalDuration > 0' => 'Validation in canProceed()',
    ],
    'src/pages/WidgetBooking.tsx' => [
        'GuestBookingModal' => 'Uses GuestBookingModal component',
    ],
];

$allGood = true;

foreach ($filesToCheck as $file => $checks) {
    $fullPath = $frontendPath . '/' . $file;

    echo "File: $file\n";

    if (!file_exists($fullPath)) {
        echo "  ❌ File does not exist!\n\n";
        $allGood = false;
        continue;
    }

    $content = file_get_contents($fullPath);

    foreach ($checks as $search => $description) {
        if (strpos($content, $search) !== false) {
            echo "  ✅ $description\n";
        } else {
            echo "  ❌ MISSING: $description\n";
            $allGood = false;
        }
    }

    echo "\n";
}

echo "========================================\n";
echo "DETAILED CHECK: GuestBookingModal.tsx\n";
echo "========================================\n\n";

$guestBookingModal = $frontendPath . '/src/components/Public/GuestBookingModal.tsx';
if (file_exists($guestBookingModal)) {
    $content = file_get_contents($guestBookingModal);

    // Check canProceed function
    echo "1. Checking canProceed() function...\n";
    if (preg_match('/case 1:.*?totalDuration.*?> 0/s', $content)) {
        echo "   ✅ canProceed() has totalDuration > 0 check\n";
    } else {
        echo "   ❌ canProceed() missing totalDuration > 0 check\n";
        $allGood = false;
    }
    echo "\n";

    // Check handleNext function
    echo "2. Checking handleNext() function...\n";
    if (preg_match('/totalDuration === 0.*?setError/s', $content)) {
        echo "   ✅ handleNext() has zero duration validation\n";
    } else {
        echo "   ❌ handleNext() missing zero duration validation\n";
        $allGood = false;
    }
    echo "\n";

    // Check UI rendering
    echo "3. Checking UI rendering...\n";
    if (preg_match('/getTotalDuration\(\) === 0.*?Ne možete rezervisati/s', $content)) {
        echo "   ✅ UI shows error message for zero duration\n";
    } else {
        echo "   ❌ UI missing error message for zero duration\n";
        $allGood = false;
    }
    echo "\n";

    // Check button disabled logic
    echo "4. Checking button disabled logic...\n";
    if (preg_match('/disabled=\{loading \|\| !canProceed\(\)\}/', $content)) {
        echo "   ✅ Button uses canProceed() for disabled state\n";
    } else {
        echo "   ❌ Button missing canProceed() check\n";
        $allGood = false;
    }
    echo "\n";
}

echo "========================================\n";
echo "BUILD CHECK\n";
echo "========================================\n\n";

$distPath = $frontendPath . '/dist';
if (!is_dir($distPath)) {
    echo "❌ dist/ folder does not exist!\n";
    echo "   Frontend has never been built.\n";
    echo "   Run: cd $frontendPath && npm run build\n\n";
    $allGood = false;
} else {
    $jsFiles = glob($distPath . '/assets/*.js');
    if (empty($jsFiles)) {
        echo "❌ No JavaScript files in dist/assets/\n\n";
        $allGood = false;
    } else {
        usort($jsFiles, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $newestFile = $jsFiles[0];
        $mtime = filemtime($newestFile);
        $age = time() - $mtime;

        echo "Newest build file: " . basename($newestFile) . "\n";
        echo "Build time: " . date('Y-m-d H:i:s', $mtime) . "\n";
        echo "Age: ";

        if ($age < 60) {
            echo $age . " seconds\n";
        } elseif ($age < 3600) {
            echo floor($age / 60) . " minutes\n";
        } elseif ($age < 86400) {
            echo floor($age / 3600) . " hours\n";
        } else {
            echo floor($age / 86400) . " days\n";
        }

        if ($age < 300) {
            echo "✅ Build is FRESH\n\n";
        } elseif ($age < 3600) {
            echo "⚠️  Build is recent but not fresh\n\n";
        } else {
            echo "❌ Build is OLD - needs rebuild\n\n";
            $allGood = false;
        }
    }
}

echo "========================================\n";
echo "SUMMARY\n";
echo "========================================\n\n";

if ($allGood) {
    echo "✅ ALL CHECKS PASSED!\n\n";
    echo "Source code has all the validation.\n";
    echo "Build is fresh.\n\n";
    echo "If widget still doesn't work, the problem is:\n";
    echo "1. Widget URL is wrong\n";
    echo "2. Browser is loading old cached files (try incognito)\n";
    echo "3. There's a JavaScript error (check console)\n";
    echo "4. Widget is using a different component\n\n";
} else {
    echo "❌ ISSUES FOUND!\n\n";
    echo "To fix:\n";
    echo "1. cd $frontendPath\n";
    echo "2. git pull origin main\n";
    echo "3. npm run build\n";
    echo "4. Run this script again\n\n";
}

echo "Next steps:\n";
echo "1. If all checks pass, test widget in incognito mode\n";
echo "2. Check browser console (F12) for JavaScript errors\n";
echo "3. Check Network tab to see which files are loaded\n";
echo "4. Send me screenshots if it still doesn't work\n\n";

