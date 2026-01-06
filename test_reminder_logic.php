#!/usr/bin/env php
<?php

/**
 * Test reminder logic for both registered users and guest bookings
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Appointment;
use Carbon\Carbon;

echo "========================================\n";
echo "Testing Reminder Logic\n";
echo "========================================\n\n";

$tomorrow = Carbon::tomorrow()->format('Y-m-d');

echo "Looking for appointments on: {$tomorrow}\n\n";

// Get all confirmed appointments for tomorrow
$appointments = Appointment::where('date', $tomorrow)
    ->where('status', 'confirmed')
    ->where(function ($query) {
        $query->whereNotNull('client_id')
            ->orWhereNotNull('client_email');
    })
    ->with(['client', 'salon', 'service', 'staff'])
    ->get();

echo "Found {$appointments->count()} confirmed appointments for tomorrow\n\n";

if ($appointments->isEmpty()) {
    echo "No appointments found. Creating test data...\n\n";

    // You can manually create test appointments here if needed
    echo "Please create test appointments manually:\n";
    echo "1. Registered user appointment (with client_id)\n";
    echo "2. Guest booking with email (with client_email)\n";
    echo "3. Guest booking without email (should be skipped)\n\n";
    exit(0);
}

echo "Analyzing appointments:\n";
echo str_repeat("-", 80) . "\n";

$registeredCount = 0;
$guestWithEmailCount = 0;
$guestWithoutEmailCount = 0;

foreach ($appointments as $appointment) {
    $isRegistered = !empty($appointment->client_id);
    $hasClientEmail = !empty($appointment->client_email);
    $hasUserEmail = $appointment->client && !empty($appointment->client->email);

    $willGetInAppNotification = $isRegistered;
    $willGetEmailReminder = $hasUserEmail || $hasClientEmail;

    echo "\nAppointment ID: {$appointment->id}\n";
    echo "  Service: {$appointment->service->name}\n";
    echo "  Time: {$appointment->time}\n";
    echo "  Client Type: " . ($isRegistered ? "Registered User" : "Guest Booking") . "\n";

    if ($isRegistered) {
        echo "  Client Name: {$appointment->client->name}\n";
        echo "  Client Email: " . ($hasUserEmail ? $appointment->client->email : "NO EMAIL") . "\n";
        $registeredCount++;
    } else {
        echo "  Guest Name: " . ($appointment->client_name ?? "N/A") . "\n";
        echo "  Guest Email: " . ($hasClientEmail ? $appointment->client_email : "NO EMAIL") . "\n";

        if ($hasClientEmail) {
            $guestWithEmailCount++;
        } else {
            $guestWithoutEmailCount++;
        }
    }

    echo "  Will get in-app notification: " . ($willGetInAppNotification ? "✓ YES" : "✗ NO") . "\n";
    echo "  Will get email reminder: " . ($willGetEmailReminder ? "✓ YES" : "✗ NO") . "\n";

    if (!$willGetEmailReminder && !$willGetInAppNotification) {
        echo "  ⚠️  WARNING: This appointment will NOT receive any reminder!\n";
    }
}

echo "\n" . str_repeat("-", 80) . "\n";
echo "\nSummary:\n";
echo "  Registered users: {$registeredCount}\n";
echo "  Guest bookings with email: {$guestWithEmailCount}\n";
echo "  Guest bookings without email: {$guestWithoutEmailCount}\n";
echo "\n";
echo "Expected reminders to be sent: " . ($registeredCount + $guestWithEmailCount) . "\n";
echo "Appointments to be skipped: {$guestWithoutEmailCount}\n";
echo "\n";

if ($guestWithoutEmailCount > 0) {
    echo "⚠️  Note: {$guestWithoutEmailCount} guest booking(s) without email will be skipped\n";
    echo "   (This is expected behavior - they should be contacted by phone)\n\n";
}

echo "To actually send reminders, run:\n";
echo "  php artisan appointments:send-reminders\n\n";
