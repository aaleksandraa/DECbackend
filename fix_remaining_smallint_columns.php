<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=================================================================\n";
echo "FIX REMAINING SMALLINT BOOLEAN COLUMNS\n";
echo "=================================================================\n\n";

$columnsToFix = [
    ['table' => 'notifications', 'column' => 'is_read', 'default' => 'false'],
    ['table' => 'service_images', 'column' => 'is_featured', 'default' => 'false'],
];

foreach ($columnsToFix as $col) {
    $table = $col['table'];
    $column = $col['column'];
    $default = $col['default'];

    echo "Processing {$table}.{$column}...\n";

    try {
        // Check current type
        $result = DB::select("
            SELECT data_type, column_default, is_nullable
            FROM information_schema.columns
            WHERE table_name = ? AND column_name = ?
        ", [$table, $column]);

        if (empty($result)) {
            echo "  ⚠️  Column not found, skipping\n\n";
            continue;
        }

        echo "  Current type: " . $result[0]->data_type . "\n";

        if ($result[0]->data_type === 'boolean') {
            echo "  ✅ Already BOOLEAN\n\n";
            continue;
        }

        // Drop CHECK constraint if exists
        $constraintName = "{$table}_{$column}_check";
        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraintName}");

        // Drop default
        DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP DEFAULT");

        // Convert type
        DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE BOOLEAN USING ({$column}::integer != 0)");

        // Set default
        DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT {$default}");

        // Set NOT NULL if it was NOT NULL
        if ($result[0]->is_nullable === 'NO') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET NOT NULL");
        }

        echo "  ✅ Converted to BOOLEAN\n\n";

    } catch (\Exception $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n\n";
    }
}

// Verify
echo "=================================================================\n";
echo "VERIFICATION\n";
echo "=================================================================\n\n";

foreach ($columnsToFix as $col) {
    $table = $col['table'];
    $column = $col['column'];

    $result = DB::select("
        SELECT data_type, column_default, is_nullable
        FROM information_schema.columns
        WHERE table_name = ? AND column_name = ?
    ", [$table, $column]);

    if (!empty($result)) {
        $status = $result[0]->data_type === 'boolean' ? '✅' : '❌';
        echo sprintf("%s %-40s %s\n",
            $status,
            "{$table}.{$column}",
            $result[0]->data_type
        );
    }
}

echo "\n✅ DONE!\n\n";
