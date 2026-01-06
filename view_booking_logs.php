<?php

/**
 * View Booking Logs
 *
 * This script displays appointment booking attempts and related logs
 * from Laravel log file.
 */

$logFile = __DIR__ . '/storage/logs/laravel.log';

if (!file_exists($logFile)) {
    echo "❌ Log file not found: {$logFile}\n";
    exit(1);
}

echo "=== Booking Logs Viewer ===\n\n";

// Get command line arguments
$filter = $argv[1] ?? 'all';
$lines = isset($argv[2]) ? (int)$argv[2] : 100;

echo "Filter: {$filter}\n";
echo "Lines: {$lines}\n";
echo "Log file: {$logFile}\n";
echo str_repeat('=', 80) . "\n\n";

// Read log file
$logContent = file_get_contents($logFile);
$logLines = explode("\n", $logContent);

// Reverse to show newest first
$logLines = array_reverse($logLines);

$matchedLines = [];
$currentEntry = '';
$entryCount = 0;

foreach ($logLines as $line) {
    // Check if this is a new log entry (starts with timestamp)
    if (preg_match('/^\[\d{4}-\d{2}-\d{2}/', $line)) {
        // Process previous entry if it matches
        if ($currentEntry && shouldIncludeEntry($currentEntry, $filter)) {
            $matchedLines[] = $currentEntry;
            $entryCount++;
            if ($entryCount >= $lines) {
                break;
            }
        }
        // Start new entry
        $currentEntry = $line;
    } else {
        // Continuation of current entry
        $currentEntry .= "\n" . $line;
    }
}

// Process last entry
if ($currentEntry && shouldIncludeEntry($currentEntry, $filter) && $entryCount < $lines) {
    $matchedLines[] = $currentEntry;
}

// Display results
if (empty($matchedLines)) {
    echo "No matching log entries found.\n";
} else {
    echo "Found " . count($matchedLines) . " matching entries:\n\n";
    echo str_repeat('=', 80) . "\n\n";

    foreach ($matchedLines as $entry) {
        echo formatLogEntry($entry);
        echo "\n" . str_repeat('-', 80) . "\n\n";
    }
}

echo "\n=== End of Logs ===\n";

/**
 * Check if log entry should be included based on filter
 */
function shouldIncludeEntry(string $entry, string $filter): bool
{
    $entry = strtolower($entry);

    switch ($filter) {
        case 'booking':
        case 'appointments':
            return strpos($entry, 'appointment') !== false;

        case 'availability':
            return strpos($entry, 'availability') !== false
                || strpos($entry, 'checking availability') !== false;

        case 'errors':
            return strpos($entry, 'error') !== false
                || strpos($entry, 'exception') !== false
                || strpos($entry, 'failed') !== false;

        case 'reminders':
            return strpos($entry, 'reminder') !== false;

        case 'emails':
            return strpos($entry, 'email') !== false
                || strpos($entry, 'mail') !== false;

        case 'notifications':
            return strpos($entry, 'notification') !== false;

        case 'cache':
            return strpos($entry, 'cache') !== false;

        case 'today':
            $today = date('Y-m-d');
            return strpos($entry, $today) !== false;

        case 'all':
        default:
            return true;
    }
}

/**
 * Format log entry for better readability
 */
function formatLogEntry(string $entry): string
{
    // Extract timestamp
    if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $entry, $matches)) {
        $timestamp = $matches[1];
        $entry = substr($entry, strlen($matches[0]));
    } else {
        $timestamp = 'Unknown';
    }

    // Extract log level
    if (preg_match('/^\s*(local|production)\.(INFO|ERROR|WARNING|DEBUG):/', $entry, $matches)) {
        $level = $matches[2];
        $entry = substr($entry, strlen($matches[0]));
    } else {
        $level = 'INFO';
    }

    // Color code by level
    $levelColor = match($level) {
        'ERROR' => "\033[31m", // Red
        'WARNING' => "\033[33m", // Yellow
        'INFO' => "\033[32m", // Green
        'DEBUG' => "\033[36m", // Cyan
        default => "\033[0m", // Default
    };
    $resetColor = "\033[0m";

    // Format output
    $output = "{$levelColor}[{$timestamp}] {$level}{$resetColor}\n";
    $output .= trim($entry);

    // Try to pretty print JSON
    if (preg_match('/\{.*\}/', $entry, $jsonMatches)) {
        $json = json_decode($jsonMatches[0], true);
        if ($json) {
            $output .= "\n\nData:\n";
            $output .= json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    }

    return $output;
}

/**
 * Display usage information
 */
function showUsage()
{
    echo <<<USAGE
Usage: php view_booking_logs.php [filter] [lines]

Filters:
  all            - Show all logs (default)
  booking        - Show appointment booking logs
  appointments   - Same as booking
  availability   - Show availability check logs
  errors         - Show only errors and exceptions
  reminders      - Show reminder logs
  emails         - Show email-related logs
  notifications  - Show notification logs
  cache          - Show cache-related logs
  today          - Show only today's logs

Lines:
  Number of log entries to show (default: 100)

Examples:
  php view_booking_logs.php booking 50
  php view_booking_logs.php errors 20
  php view_booking_logs.php today
  php view_booking_logs.php all 200

USAGE;
}

// Show usage if help requested
if (isset($argv[1]) && in_array($argv[1], ['-h', '--help', 'help'])) {
    showUsage();
    exit(0);
}
