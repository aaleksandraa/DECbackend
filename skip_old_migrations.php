<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Marking Old Migrations as Run ===\n\n";

// Migrations to skip (already run or problematic)
$migrationsToSkip = [
    '2024_12_27_200000_convert_smallint_to_boolean',
    '2024_12_28_000000_convert_smallint_to_boolean_safe',
    '2024_12_28_100000_convert_remaining_smallint_to_boolean',
];

foreach ($migrationsToSkip as $migration) {
    // Check if already in migrations table
    $exists = DB::table('migrations')
        ->where('migration', $migration)
        ->exists();

    if ($exists) {
        echo "✓ {$migration} - already marked as run\n";
    } else {
        // Insert into migrations table
        DB::table('migrations')->insert([
            'migration' => $migration,
            'batch' => 999, // High batch number to indicate manual insertion
        ]);
        echo "✓ {$migration} - marked as run (skipped)\n";
    }
}

echo "\n=== Done ===\n";
echo "Old migrations are now marked as run.\n";
echo "You can now run: php artisan migrate\n";
