<?php

/**
 * Check Email Notifications Enabled Status
 *
 * This script checks if salons have email_notifications_enabled set to true
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Salon;
use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "Email Notifications Status Check\n";
echo "========================================\n\n";

// Check if column exists
echo "Step 1: Checking if email_notifications_enabled column exists...\n";
try {
    $result = DB::select("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns
        WHERE table_name = 'salons'
        AND column_name = 'email_notifications_enabled'
    ");

    if (!empty($result)) {
        $column = $result[0];
        echo "✓ Column exists\n";
        echo "  Type: {$column->data_type}\n";
        echo "  Nullable: {$column->is_nullable}\n";
        echo "  Default: " . ($column->column_default ?? 'none') . "\n";
    } else {
        echo "✗ Column 'email_notifications_enabled' NOT FOUND in salons table\n";
        echo "\nThis is the problem! The column doesn't exist.\n";
        echo "Emails are only sent if this column is true.\n\n";
        echo "Solution: Add migration to create this column with default true\n";
    }
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Check salon values
echo "Step 2: Checking salon email notification settings...\n";
try {
    $salons = Salon::with('owner')->get();

    echo "Total salons: {$salons->count()}\n\n";

    $enabled = 0;
    $disabled = 0;
    $null = 0;

    foreach ($salons as $salon) {
        $status = 'UNKNOWN';

        // Try to access the property
        try {
            if (isset($salon->email_notifications_enabled)) {
                if ($salon->email_notifications_enabled === true) {
                    $status = '✓ ENABLED';
                    $enabled++;
                } elseif ($salon->email_notifications_enabled === false) {
                    $status = '✗ DISABLED';
                    $disabled++;
                } else {
                    $status = '? NULL';
                    $null++;
                }
            } else {
                $status = '✗ COLUMN MISSING';
            }
        } catch (\Exception $e) {
            $status = '✗ ERROR: ' . $e->getMessage();
        }

        $ownerEmail = $salon->owner->email ?? 'N/A';
        echo "Salon: {$salon->name} (ID: {$salon->id})\n";
        echo "  Owner: {$ownerEmail}\n";
        echo "  Email Notifications: {$status}\n\n";
    }

    echo "Summary:\n";
    echo "  Enabled: {$enabled}\n";
    echo "  Disabled: {$disabled}\n";
    echo "  NULL: {$null}\n";

} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
echo "\n";

echo "========================================\n";
echo "Analysis Complete\n";
echo "========================================\n\n";

echo "What this means:\n";
echo "- If column is missing: Emails are NEVER sent to salon owners\n";
echo "- If column is NULL or false: Emails are NOT sent\n";
echo "- If column is true: Emails ARE sent\n\n";

echo "Solution:\n";
echo "1. Create migration to add email_notifications_enabled column\n";
echo "2. Set default value to true\n";
echo "3. Update existing salons to true\n\n";
