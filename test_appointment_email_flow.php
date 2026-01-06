<?php

/**
 * Test Appointment Email Flow
 *
 * This script simulates appointment creation and checks if emails would be sent
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Log;

echo "========================================\n";
echo "Appointment Email Flow Test\n";
echo "========================================\n\n";

// Get first salon
$salon = Salon::with(['owner', 'staff.user'])->first();

if (!$salon) {
    echo "✗ No salons found in database\n";
    exit(1);
}

echo "Testing with Salon: {$salon->name} (ID: {$salon->id})\n\n";

// Check salon owner
echo "Step 1: Checking Salon Owner...\n";
$owner = $salon->owner;
if ($owner) {
    echo "✓ Owner found: {$owner->name}\n";
    echo "  Email: {$owner->email}\n";
    echo "  Email notifications enabled: " . ($salon->email_notifications_enabled ? 'YES' : 'NO') . "\n";

    if ($owner->email && $salon->email_notifications_enabled) {
        echo "  ✓ Owner WILL receive emails\n";
    } else {
        echo "  ✗ Owner WILL NOT receive emails\n";
        if (!$owner->email) echo "    Reason: No email address\n";
        if (!$salon->email_notifications_enabled) echo "    Reason: Email notifications disabled\n";
    }
} else {
    echo "✗ No owner found\n";
}
echo "\n";

// Check staff members
echo "Step 2: Checking Staff Members...\n";
$staff = $salon->staff;
echo "Total staff: {$staff->count()}\n\n";

foreach ($staff as $member) {
    echo "Staff: {$member->name} (ID: {$member->id})\n";

    if ($member->user_id) {
        $staffUser = User::find($member->user_id);
        if ($staffUser) {
            echo "  ✓ Has user account: {$staffUser->name}\n";
            echo "  Email: {$staffUser->email}\n";

            if ($staffUser->email) {
                echo "  ✓ Staff member WILL receive emails\n";
            } else {
                echo "  ✗ Staff member WILL NOT receive emails (no email)\n";
            }
        } else {
            echo "  ✗ User account not found (user_id: {$member->user_id})\n";
        }
    } else {
        echo "  ✗ No user account linked (user_id is NULL)\n";
        echo "  ✗ Staff member WILL NOT receive emails\n";
    }
    echo "\n";
}

// Check mail configuration
echo "Step 3: Checking Mail Configuration...\n";
$mailDriver = env('MAIL_MAILER');
$mailHost = env('MAIL_HOST');
$mailPort = env('MAIL_PORT');
$mailUsername = env('MAIL_USERNAME');
$mailFrom = env('MAIL_FROM_ADDRESS');

echo "Mail Driver: " . ($mailDriver ?? 'NOT SET') . "\n";
echo "Mail Host: " . ($mailHost ?? 'NOT SET') . "\n";
echo "Mail Port: " . ($mailPort ?? 'NOT SET') . "\n";
echo "Mail Username: " . ($mailUsername ?? 'NOT SET') . "\n";
echo "Mail From: " . ($mailFrom ?? 'NOT SET') . "\n";

if ($mailDriver && $mailHost && $mailFrom) {
    echo "✓ Mail configuration looks OK\n";
} else {
    echo "✗ Mail configuration incomplete\n";
}
echo "\n";

// Summary
echo "========================================\n";
echo "Summary\n";
echo "========================================\n\n";

$ownerWillReceive = $owner && $owner->email && $salon->email_notifications_enabled;
$staffCount = $staff->filter(fn($s) => $s->user_id && $s->user && $s->user->email)->count();

echo "When a new appointment is created:\n\n";

echo "1. Client/Guest:\n";
echo "   ✓ WILL receive confirmation email\n";
echo "   (sent directly in controller)\n\n";

echo "2. Salon Owner:\n";
if ($ownerWillReceive) {
    echo "   ✓ WILL receive notification email\n";
    echo "   To: {$owner->email}\n";
} else {
    echo "   ✗ WILL NOT receive notification email\n";
    if (!$owner) echo "   Reason: No owner found\n";
    elseif (!$owner->email) echo "   Reason: Owner has no email\n";
    elseif (!$salon->email_notifications_enabled) echo "   Reason: Email notifications disabled\n";
}
echo "\n";

echo "3. Staff Members:\n";
if ($staffCount > 0) {
    echo "   ✓ {$staffCount} staff member(s) WILL receive notification email\n";
    foreach ($staff as $member) {
        if ($member->user_id && $member->user && $member->user->email) {
            echo "   - {$member->name}: {$member->user->email}\n";
        }
    }
} else {
    echo "   ✗ NO staff members will receive emails\n";
    echo "   Reason: No staff with linked user accounts and email addresses\n";
}
echo "\n";

// Test recommendation
echo "========================================\n";
echo "Next Steps\n";
echo "========================================\n\n";

echo "To test email sending:\n";
echo "1. Create a test appointment through the app/widget\n";
echo "2. Check these email inboxes:\n";
if ($ownerWillReceive) {
    echo "   - Owner: {$owner->email}\n";
}
foreach ($staff as $member) {
    if ($member->user_id && $member->user && $member->user->email) {
        echo "   - Staff ({$member->name}): {$member->user->email}\n";
    }
}
echo "\n";
echo "3. Check Laravel logs:\n";
echo "   tail -f storage/logs/laravel.log | grep -i 'mail\\|appointment'\n";
echo "\n";
echo "4. Check mail queue (if using queue):\n";
echo "   php artisan queue:work\n";
echo "\n";
