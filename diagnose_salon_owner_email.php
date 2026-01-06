<?php

/**
 * Diagnose Salon Owner Email Issue
 *
 * This script checks why salon owner is not receiving emails
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Salon;
use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "Salon Owner Email Diagnosis\n";
echo "========================================\n\n";

// Get all salons
$salons = Salon::with('owner')->get();

echo "Total salons: {$salons->count()}\n\n";

foreach ($salons as $salon) {
    echo "Salon: {$salon->name} (ID: {$salon->id})\n";
    echo str_repeat("-", 50) . "\n";

    // Check owner
    $owner = $salon->owner;
    if (!$owner) {
        echo "✗ NO OWNER FOUND\n";
        echo "  This salon has no owner!\n\n";
        continue;
    }

    echo "Owner: {$owner->name} (ID: {$owner->id})\n";
    echo "Owner Email: " . ($owner->email ?? 'NULL') . "\n";

    // Check email_notifications_enabled
    echo "\nChecking email_notifications_enabled:\n";

    // Get raw value from database
    $rawValue = DB::table('salons')
        ->where('id', $salon->id)
        ->value('email_notifications_enabled');

    echo "  Raw DB value: ";
    var_dump($rawValue);
    echo "  Type: " . gettype($rawValue) . "\n";

    // Check through model
    $modelValue = $salon->email_notifications_enabled;
    echo "  Model value: ";
    var_dump($modelValue);
    echo "  Type: " . gettype($modelValue) . "\n";

    // Check condition
    echo "\nCondition Check:\n";
    echo "  \$owner->email: " . ($owner->email ? 'TRUE' : 'FALSE') . "\n";
    echo "  \$salon->email_notifications_enabled: " . ($salon->email_notifications_enabled ? 'TRUE' : 'FALSE') . "\n";

    $condition = $owner->email && $salon->email_notifications_enabled;
    echo "  Combined (\$owner->email && \$salon->email_notifications_enabled): " . ($condition ? 'TRUE' : 'FALSE') . "\n";

    // Result
    echo "\nResult:\n";
    if ($condition) {
        echo "  ✓ Owner WILL receive emails\n";
        echo "  Email will be sent to: {$owner->email}\n";
    } else {
        echo "  ✗ Owner WILL NOT receive emails\n";
        echo "  Reasons:\n";
        if (!$owner->email) {
            echo "    - Owner has no email address\n";
        }
        if (!$salon->email_notifications_enabled) {
            echo "    - email_notifications_enabled is FALSE or NULL\n";
            echo "      Raw value: ";
            var_dump($rawValue);
        }
    }

    echo "\n";
}

// Check column definition
echo "========================================\n";
echo "Column Definition Check\n";
echo "========================================\n\n";

try {
    $columnInfo = DB::select("
        SELECT
            column_name,
            data_type,
            is_nullable,
            column_default
        FROM information_schema.columns
        WHERE table_name = 'salons'
        AND column_name = 'email_notifications_enabled'
    ");

    if (!empty($columnInfo)) {
        $col = $columnInfo[0];
        echo "Column: {$col->column_name}\n";
        echo "Type: {$col->data_type}\n";
        echo "Nullable: {$col->is_nullable}\n";
        echo "Default: " . ($col->column_default ?? 'none') . "\n";
    } else {
        echo "✗ Column 'email_notifications_enabled' NOT FOUND\n";
    }
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Check for NULL or false values
echo "========================================\n";
echo "Problem Detection\n";
echo "========================================\n\n";

$nullCount = DB::table('salons')
    ->whereNull('email_notifications_enabled')
    ->count();

$falseCount = DB::table('salons')
    ->where('email_notifications_enabled', false)
    ->count();

$trueCount = DB::table('salons')
    ->where('email_notifications_enabled', true)
    ->count();

echo "Salons with email_notifications_enabled:\n";
echo "  TRUE: {$trueCount}\n";
echo "  FALSE: {$falseCount}\n";
echo "  NULL: {$nullCount}\n\n";

if ($falseCount > 0 || $nullCount > 0) {
    echo "⚠️  PROBLEM FOUND!\n";
    echo "Some salons have email_notifications_enabled set to FALSE or NULL.\n";
    echo "This is why owner emails are not being sent.\n\n";

    echo "Solution:\n";
    echo "Run this SQL to fix:\n";
    echo "UPDATE salons SET email_notifications_enabled = true WHERE email_notifications_enabled IS NULL OR email_notifications_enabled = false;\n\n";
}

echo "========================================\n";
echo "Diagnosis Complete\n";
echo "========================================\n";
