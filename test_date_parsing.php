<?php

require __DIR__ . '/vendor/autoload.php';

use Carbon\Carbon;

function testDateParsing($date) {
    echo "Testing date: '$date'\n";
    echo "Type: " . gettype($date) . "\n";
    echo "Length: " . strlen($date) . "\n";

    // Trim whitespace
    $date = trim($date);

    // Handle Excel numeric date format
    if (is_numeric($date)) {
        echo "  -> Numeric format detected\n";
        try {
            $excelEpoch = Carbon::create(1900, 1, 1);
            $parsed = $excelEpoch->addDays((int)$date - 2);
            echo "  -> Parsed as: " . $parsed->format('Y-m-d') . "\n";
            return true;
        } catch (\Exception $e) {
            echo "  -> Failed: " . $e->getMessage() . "\n";
        }
    }

    $formats = [
        'Y-m-d',      // 2026-01-16
        'd.m.Y',      // 16.1.2026
        'd.n.Y',      // 16.1.2026 (single digit month)
        'j.n.Y',      // 6.1.2026 (single digit day and month)
        'd/m/Y',      // 16/01/2026
        'd/n/Y',      // 16/1/2026 (single digit month)
        'j/n/Y',      // 6/1/2026 (single digit day and month)
        'd-m-Y',      // 16-01-2026
        'd-n-Y',      // 16-1-2026 (single digit month)
        'j-n-Y',      // 6-1-2026 (single digit day and month)
        'd.m.y',      // 16.1.26 (2-digit year)
        'j.n.y',      // 6.1.26 (2-digit year)
    ];

    foreach ($formats as $format) {
        try {
            $parsed = Carbon::createFromFormat($format, $date);
            if ($parsed && $parsed->year >= 1900 && $parsed->year <= 2100) {
                echo "  -> Matched format: $format\n";
                echo "  -> Parsed as: " . $parsed->format('Y-m-d') . "\n";
                return true;
            }
        } catch (\Exception $e) {
            // Continue
        }
    }

    echo "  -> NO MATCH FOUND\n";
    return false;
}

// Test various date formats
$testDates = [
    '2026-01-16',
    '16.1.2026',
    '6.1.2026',
    '16.01.2026',
    '16/1/2026',
    '16-1-2026',
    '45674', // Excel numeric format for 2026-01-16
];

foreach ($testDates as $date) {
    testDateParsing($date);
    echo "\n";
}
