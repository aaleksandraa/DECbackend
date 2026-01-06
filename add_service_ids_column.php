<?php

/**
 * Add service_ids Column to Appointments Table
 *
 * This script adds the service_ids JSON column to the appointments table.
 * Run this BEFORE using multi-service appointments.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "========================================\n";
echo "Add service_ids Column\n";
echo "========================================\n\n";

try {
    // Check if column exists
    $columnExists = Schema::hasColumn('appointments', 'service_ids');

    if ($columnExists) {
        echo "✅ Column 'service_ids' already exists!\n\n";
    } else {
        echo "Adding 'service_ids' column...\n";

        // Add column using raw SQL (works with PostgreSQL)
        DB::statement('ALTER TABLE appointments ADD COLUMN service_ids JSON NULL');

        echo "✅ Column 'service_ids' added successfully!\n\n";
    }

    // Check if service_id is nullable
    echo "Checking service_id column...\n";

    $columns = DB::select("
        SELECT column_name, is_nullable, data_type
        FROM information_schema.columns
        WHERE table_name = 'appointments'
        AND column_name IN ('service_id', 'service_ids')
        ORDER BY column_name
    ");

    echo "\nCurrent column status:\n";
    foreach ($columns as $column) {
        $nullable = $column->is_nullable === 'YES' ? '✅ Nullable' : '❌ NOT NULL';
        echo "  - {$column->column_name} ({$column->data_type}): {$nullable}\n";
    }

    // Make service_id nullable if it's not
    $serviceIdColumn = collect($columns)->firstWhere('column_name', 'service_id');
    if ($serviceIdColumn && $serviceIdColumn->is_nullable === 'NO') {
        echo "\nMaking service_id nullable...\n";
        DB::statement('ALTER TABLE appointments ALTER COLUMN service_id DROP NOT NULL');
        echo "✅ service_id is now nullable!\n\n";
    } else {
        echo "\n✅ service_id is already nullable!\n\n";
    }

    echo "========================================\n";
    echo "✅ Database Updated Successfully!\n";
    echo "========================================\n\n";
    echo "You can now use multi-service appointments.\n\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n\n";
    exit(1);
}
