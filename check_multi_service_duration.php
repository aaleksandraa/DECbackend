<?php

/**
 * Check Multi-Service Appointment Duration
 *
 * This script checks if appointments with multiple services have correct end_time
 * based on total duration of all services.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;

echo "========================================\n";
echo "Multi-Service Duration Check\n";
echo "========================================\n\n";

// Get all appointments with service_ids (multi-service)
$multiServiceAppointments = Appointment::whereNotNull('service_ids')
    ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
    ->get();

echo "Found " . $multiServiceAppointments->count() . " multi-service appointments\n\n";

$issues = [];

foreach ($multiServiceAppointments as $appointment) {
    $serviceIds = $appointment->service_ids;

    if (empty($serviceIds) || !is_array($serviceIds)) {
        continue;
    }

    // Load services and calculate total duration
    $services = Service::whereIn('id', $serviceIds)->get();
    $totalDuration = 0;
    $serviceNames = [];

    foreach ($services as $service) {
        $totalDuration += $service->duration;
        $serviceNames[] = $service->name . " ({$service->duration}min)";
    }

    // Calculate expected end_time
    $startParts = explode(':', $appointment->time);
    $startMinutes = (int)$startParts[0] * 60 + (int)$startParts[1];
    $endMinutes = $startMinutes + $totalDuration;
    $expectedEndTime = sprintf('%02d:%02d', floor($endMinutes / 60), $endMinutes % 60);

    // Check if end_time matches
    if ($appointment->end_time !== $expectedEndTime) {
        $issues[] = [
            'id' => $appointment->id,
            'date' => $appointment->date->format('d.m.Y'),
            'time' => $appointment->time,
            'current_end_time' => $appointment->end_time,
            'expected_end_time' => $expectedEndTime,
            'total_duration' => $totalDuration,
            'services' => $serviceNames,
            'staff' => $appointment->staff->name ?? 'N/A',
        ];
    }
}

if (empty($issues)) {
    echo "✅ All multi-service appointments have correct end_time!\n\n";
} else {
    echo "❌ Found " . count($issues) . " appointments with incorrect end_time:\n\n";

    foreach ($issues as $issue) {
        echo "Appointment ID: {$issue['id']}\n";
        echo "  Date: {$issue['date']} {$issue['time']}\n";
        echo "  Staff: {$issue['staff']}\n";
        echo "  Services: " . implode(', ', $issue['services']) . "\n";
        echo "  Total Duration: {$issue['total_duration']} min\n";
        echo "  Current end_time: {$issue['current_end_time']}\n";
        echo "  Expected end_time: {$issue['expected_end_time']}\n";
        echo "  ❌ MISMATCH!\n\n";
    }

    echo "========================================\n";
    echo "Fix Script\n";
    echo "========================================\n\n";
    echo "Run this to fix all issues:\n";
    echo "php fix_multi_service_end_times.php\n\n";
}

// Also check single service appointments
echo "========================================\n";
echo "Checking Single Service Appointments\n";
echo "========================================\n\n";

$singleServiceAppointments = Appointment::whereNotNull('service_id')
    ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
    ->with('service')
    ->get();

echo "Found " . $singleServiceAppointments->count() . " single-service appointments\n\n";

$singleIssues = [];

foreach ($singleServiceAppointments as $appointment) {
    if (!$appointment->service) {
        continue;
    }

    $duration = $appointment->service->duration;

    // Calculate expected end_time
    $startParts = explode(':', $appointment->time);
    $startMinutes = (int)$startParts[0] * 60 + (int)$startParts[1];
    $endMinutes = $startMinutes + $duration;
    $expectedEndTime = sprintf('%02d:%02d', floor($endMinutes / 60), $endMinutes % 60);

    // Check if end_time matches
    if ($appointment->end_time !== $expectedEndTime) {
        $singleIssues[] = [
            'id' => $appointment->id,
            'date' => $appointment->date->format('d.m.Y'),
            'time' => $appointment->time,
            'current_end_time' => $appointment->end_time,
            'expected_end_time' => $expectedEndTime,
            'duration' => $duration,
            'service' => $appointment->service->name,
            'staff' => $appointment->staff->name ?? 'N/A',
        ];
    }
}

if (empty($singleIssues)) {
    echo "✅ All single-service appointments have correct end_time!\n\n";
} else {
    echo "❌ Found " . count($singleIssues) . " single-service appointments with incorrect end_time:\n\n";

    foreach (array_slice($singleIssues, 0, 10) as $issue) {
        echo "Appointment ID: {$issue['id']}\n";
        echo "  Date: {$issue['date']} {$issue['time']}\n";
        echo "  Staff: {$issue['staff']}\n";
        echo "  Service: {$issue['service']} ({$issue['duration']} min)\n";
        echo "  Current end_time: {$issue['current_end_time']}\n";
        echo "  Expected end_time: {$issue['expected_end_time']}\n";
        echo "  ❌ MISMATCH!\n\n";
    }

    if (count($singleIssues) > 10) {
        echo "... and " . (count($singleIssues) - 10) . " more\n\n";
    }
}

echo "========================================\n";
echo "Summary\n";
echo "========================================\n\n";
echo "Multi-service issues: " . count($issues) . "\n";
echo "Single-service issues: " . count($singleIssues) . "\n";
echo "Total issues: " . (count($issues) + count($singleIssues)) . "\n\n";

if (count($issues) + count($singleIssues) > 0) {
    echo "Run fix script to correct all end_time values:\n";
    echo "php fix_multi_service_end_times.php\n\n";
}
