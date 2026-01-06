<?php

/**
 * Real-time Booking Monitor
 *
 * This script monitors Laravel log file in real-time for booking-related events
 */

$logFile = __DIR__ . '/storage/logs/laravel.log';

if (!file_exists($logFile)) {
    echo "❌ Log file not found: {$logFile}\n";
    exit(1);
}

echo "=== Real-time Booking Monitor ===\n";
echo "Monitoring: {$logFile}\n";
echo "Press Ctrl+C to stop\n";
echo str_repeat('=', 80) . "\n\n";

// Get file size
$lastSize = filesize($logFile);
$lastPosition = $lastSize;

// Move to end of file
$handle = fopen($logFile, 'r');
fseek($handle, $lastPosition);

echo "Waiting for new log entries...\n\n";

while (true) {
    clearstatcache();
    $currentSize = filesize($logFile);

    if ($currentSize > $lastSize) {
        // File has grown, read new content
        $newContent = fread($handle, $currentSize - $lastSize);
        $lines = explode("\n", $newContent);

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            // Check if line is booking-related
            if (isBookingRelated($line)) {
                echo formatLine($line) . "\n";
            }
        }

        $lastSize = $currentSize;
    } elseif ($currentSize < $lastSize) {
        // File was truncated or rotated
        echo "\n⚠️  Log file was rotated or truncated\n\n";
        fclose($handle);
        $handle = fopen($logFile, 'r');
        $lastSize = 0;
        $lastPosition = 0;
    }

    usleep(500000); // Sleep 0.5 seconds
}

fclose($handle);

/**
 * Check if line is booking-related
 */
function isBookingRelated(string $line): bool
{
    $keywords = [
        'appointment',
        'booking',
        'availability',
        'time slot',
        'reservation',
        'termin',
        'zakazivanje',
    ];

    $lineLower = strtolower($line);

    foreach ($keywords as $keyword) {
        if (strpos($lineLower, $keyword) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Format line with colors
 */
function formatLine(string $line): string
{
    // Extract timestamp
    if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
        $timestamp = $matches[1];
    } else {
        $timestamp = date('Y-m-d H:i:s');
    }

    // Determine color based on content
    if (stripos($line, 'error') !== false || stripos($line, 'exception') !== false) {
        $color = "\033[31m"; // Red
        $icon = "❌";
    } elseif (stripos($line, 'warning') !== false) {
        $color = "\033[33m"; // Yellow
        $icon = "⚠️ ";
    } elseif (stripos($line, 'created') !== false || stripos($line, 'success') !== false) {
        $color = "\033[32m"; // Green
        $icon = "✅";
    } elseif (stripos($line, 'checking') !== false || stripos($line, 'availability') !== false) {
        $color = "\033[36m"; // Cyan
        $icon = "🔍";
    } else {
        $color = "\033[0m"; // Default
        $icon = "ℹ️ ";
    }

    $resetColor = "\033[0m";

    return "{$color}[{$timestamp}] {$icon} {$line}{$resetColor}";
}
