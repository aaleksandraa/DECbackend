<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=================================================================\n";
echo "DATABASE BACKUP\n";
echo "=================================================================\n\n";

$timestamp = date('Y-m-d_H-i-s');
$backupFile = "backup_frizerino_{$timestamp}.sql";

echo "Creating backup: {$backupFile}\n\n";

// Get database credentials from .env
$dbHost = env('DB_HOST', 'localhost');
$dbPort = env('DB_PORT', '5432');
$dbDatabase = env('DB_DATABASE', 'frizerino');
$dbUsername = env('DB_USERNAME', 'postgres');
$dbPassword = env('DB_PASSWORD', 'root');

// Try to use pg_dump
$pgDumpPath = 'C:\\Program Files\\PostgreSQL\\17\\bin\\pg_dump.exe';

if (file_exists($pgDumpPath)) {
    echo "Using pg_dump from: {$pgDumpPath}\n";

    // Set password environment variable
    putenv("PGPASSWORD={$dbPassword}");

    $command = "\"{$pgDumpPath}\" -h {$dbHost} -p {$dbPort} -U {$dbUsername} -d {$dbDatabase} -F p -f \"{$backupFile}\"";

    echo "Executing backup...\n";
    exec($command, $output, $returnCode);

    if ($returnCode === 0 && file_exists($backupFile)) {
        $fileSize = filesize($backupFile);
        $fileSizeMB = round($fileSize / 1024 / 1024, 2);

        echo "\n✅ Backup successful!\n";
        echo "   File: {$backupFile}\n";
        echo "   Size: {$fileSizeMB} MB\n\n";

        // Show some stats
        echo "Database statistics:\n";
        echo str_repeat('-', 65) . "\n";

        $tables = DB::select("
            SELECT
                schemaname,
                tablename,
                pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size,
                (SELECT COUNT(*) FROM information_schema.columns WHERE table_name = tablename) as columns
            FROM pg_tables
            WHERE schemaname = 'public'
            ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC
            LIMIT 10
        ");

        foreach ($tables as $table) {
            echo sprintf("%-30s %10s (%2d columns)\n",
                $table->tablename,
                $table->size,
                $table->columns
            );
        }

        echo str_repeat('-', 65) . "\n\n";

        // Count records in main tables
        echo "Record counts:\n";
        echo str_repeat('-', 65) . "\n";

        $mainTables = ['users', 'salons', 'staff', 'services', 'appointments', 'reviews'];
        foreach ($mainTables as $tableName) {
            try {
                $count = DB::table($tableName)->count();
                echo sprintf("%-30s %10d records\n", $tableName, $count);
            } catch (\Exception $e) {
                echo sprintf("%-30s %10s\n", $tableName, "N/A");
            }
        }

        echo str_repeat('-', 65) . "\n\n";

        echo "To restore this backup:\n";
        echo "  psql -U postgres -d frizerino < {$backupFile}\n\n";

    } else {
        echo "\n❌ Backup failed!\n";
        echo "Return code: {$returnCode}\n";
        if (!empty($output)) {
            echo "Output: " . implode("\n", $output) . "\n";
        }
    }
} else {
    echo "❌ pg_dump not found at: {$pgDumpPath}\n";
    echo "\nPlease install PostgreSQL or update the path in this script.\n";
    echo "Common locations:\n";
    echo "  - C:\\Program Files\\PostgreSQL\\16\\bin\\pg_dump.exe\n";
    echo "  - C:\\Program Files\\PostgreSQL\\15\\bin\\pg_dump.exe\n";
    echo "  - C:\\Program Files\\PostgreSQL\\14\\bin\\pg_dump.exe\n";
}
