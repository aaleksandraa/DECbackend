#!/usr/bin/env php
<?php

/**
 * Clear failed appointment reminder jobs from the queue
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Clearing failed appointment reminder jobs...\n\n";

// Get count of failed jobs
$failedCount = DB::table('failed_jobs')->count();
echo "Total failed jobs: {$failedCount}\n";

// Get count of reminder-related failed jobs
$reminderFailedCount = DB::table('failed_jobs')
    ->where('payload', 'like', '%SendAppointmentReminder%')
    ->count();
echo "Failed reminder jobs: {$reminderFailedCount}\n\n";

if ($reminderFailedCount > 0) {
    // Delete failed reminder jobs
    $deleted = DB::table('failed_jobs')
        ->where('payload', 'like', '%SendAppointmentReminder%')
        ->delete();

    echo "✓ Deleted {$deleted} failed reminder jobs\n";
} else {
    echo "No failed reminder jobs to delete\n";
}

echo "\nDone!\n";
