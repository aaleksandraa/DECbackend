<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=================================================================\n";
echo "MIGRATION ANALYSIS\n";
echo "=================================================================\n\n";

// Get all migration files
$migrationPath = __DIR__ . '/database/migrations';
$migrationFiles = glob($migrationPath . '/*.php');

// Get executed migrations from database
$executedMigrations = DB::table('migrations')
    ->orderBy('batch')
    ->orderBy('id')
    ->get()
    ->pluck('migration')
    ->toArray();

echo "Total migration files: " . count($migrationFiles) . "\n";
echo "Executed migrations: " . count($executedMigrations) . "\n\n";

// Find pending migrations
$pendingMigrations = [];
foreach ($migrationFiles as $file) {
    $filename = basename($file, '.php');
    if (!in_array($filename, $executedMigrations)) {
        $pendingMigrations[] = $filename;
    }
}

if (!empty($pendingMigrations)) {
    echo "⚠️  PENDING MIGRATIONS (" . count($pendingMigrations) . "):\n";
    echo str_repeat('-', 65) . "\n";
    foreach ($pendingMigrations as $migration) {
        echo "  - {$migration}\n";
    }
    echo str_repeat('-', 65) . "\n\n";
} else {
    echo "✅ All migration files have been executed\n\n";
}

// Check database schema
echo "=================================================================\n";
echo "DATABASE SCHEMA ANALYSIS\n";
echo "=================================================================\n\n";

// Get all tables
$tables = DB::select("
    SELECT tablename
    FROM pg_tables
    WHERE schemaname = 'public'
    AND tablename NOT IN ('migrations', 'password_reset_tokens', 'failed_jobs', 'jobs', 'job_batches')
    ORDER BY tablename
");

echo "Application tables: " . count($tables) . "\n\n";

// Check for common issues
$issues = [];

// 1. Check for SMALLINT boolean columns
echo "Checking for SMALLINT boolean columns...\n";
$smallintColumns = DB::select("
    SELECT
        table_name,
        column_name,
        data_type,
        column_default
    FROM information_schema.columns
    WHERE table_schema = 'public'
    AND data_type = 'smallint'
    AND (
        column_name LIKE '%is_%'
        OR column_name LIKE '%has_%'
        OR column_name LIKE '%can_%'
        OR column_name LIKE '%accepted%'
        OR column_name LIKE '%enabled%'
        OR column_name LIKE '%active%'
        OR column_name LIKE '%public%'
        OR column_name LIKE '%verified%'
        OR column_name LIKE '%featured%'
        OR column_name LIKE '%primary%'
        OR column_name LIKE '%read%'
    )
    ORDER BY table_name, column_name
");

if (!empty($smallintColumns)) {
    echo "⚠️  Found " . count($smallintColumns) . " SMALLINT columns that should be BOOLEAN:\n";
    echo str_repeat('-', 65) . "\n";
    foreach ($smallintColumns as $col) {
        echo sprintf("  %-30s %-20s %s\n",
            $col->table_name . '.' . $col->column_name,
            $col->data_type,
            $col->column_default ?? 'no default'
        );
        $issues[] = "SMALLINT boolean: {$col->table_name}.{$col->column_name}";
    }
    echo str_repeat('-', 65) . "\n\n";
} else {
    echo "✅ No SMALLINT boolean columns found\n\n";
}

// 2. Check for NULL values in NOT NULL columns
echo "Checking for NULL constraint violations...\n";
$nullIssues = [];

foreach ($tables as $table) {
    $tableName = $table->tablename;

    // Get NOT NULL columns
    $notNullColumns = DB::select("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_name = ?
        AND is_nullable = 'NO'
        AND column_default IS NULL
        AND column_name NOT IN ('id', 'created_at', 'updated_at')
    ", [$tableName]);

    foreach ($notNullColumns as $col) {
        try {
            $nullCount = DB::table($tableName)
                ->whereNull($col->column_name)
                ->count();

            if ($nullCount > 0) {
                $nullIssues[] = "{$tableName}.{$col->column_name} has {$nullCount} NULL values";
            }
        } catch (\Exception $e) {
            // Skip if table doesn't exist or other error
        }
    }
}

if (!empty($nullIssues)) {
    echo "⚠️  Found " . count($nullIssues) . " NULL constraint violations:\n";
    echo str_repeat('-', 65) . "\n";
    foreach ($nullIssues as $issue) {
        echo "  - {$issue}\n";
    }
    echo str_repeat('-', 65) . "\n\n";
} else {
    echo "✅ No NULL constraint violations found\n\n";
}

// 3. Check for missing indexes on foreign keys
echo "Checking for missing indexes on foreign keys...\n";
$foreignKeys = DB::select("
    SELECT
        tc.table_name,
        kcu.column_name,
        ccu.table_name AS foreign_table_name,
        ccu.column_name AS foreign_column_name
    FROM information_schema.table_constraints AS tc
    JOIN information_schema.key_column_usage AS kcu
        ON tc.constraint_name = kcu.constraint_name
        AND tc.table_schema = kcu.table_schema
    JOIN information_schema.constraint_column_usage AS ccu
        ON ccu.constraint_name = tc.constraint_name
        AND ccu.table_schema = tc.table_schema
    WHERE tc.constraint_type = 'FOREIGN KEY'
    AND tc.table_schema = 'public'
    ORDER BY tc.table_name, kcu.column_name
");

$missingIndexes = [];
foreach ($foreignKeys as $fk) {
    // Check if index exists
    $indexExists = DB::select("
        SELECT 1
        FROM pg_indexes
        WHERE schemaname = 'public'
        AND tablename = ?
        AND indexdef LIKE ?
    ", [$fk->table_name, "%{$fk->column_name}%"]);

    if (empty($indexExists)) {
        $missingIndexes[] = "{$fk->table_name}.{$fk->column_name} (FK to {$fk->foreign_table_name})";
    }
}

if (!empty($missingIndexes)) {
    echo "⚠️  Found " . count($missingIndexes) . " foreign keys without indexes:\n";
    echo str_repeat('-', 65) . "\n";
    foreach ($missingIndexes as $idx) {
        echo "  - {$idx}\n";
    }
    echo str_repeat('-', 65) . "\n\n";
} else {
    echo "✅ All foreign keys have indexes\n\n";
}

// 4. Check for tables without primary keys
echo "Checking for tables without primary keys...\n";
$tablesWithoutPK = [];

foreach ($tables as $table) {
    $tableName = $table->tablename;

    $hasPK = DB::select("
        SELECT 1
        FROM information_schema.table_constraints
        WHERE table_name = ?
        AND constraint_type = 'PRIMARY KEY'
    ", [$tableName]);

    if (empty($hasPK)) {
        $tablesWithoutPK[] = $tableName;
    }
}

if (!empty($tablesWithoutPK)) {
    echo "⚠️  Found " . count($tablesWithoutPK) . " tables without primary keys:\n";
    echo str_repeat('-', 65) . "\n";
    foreach ($tablesWithoutPK as $tbl) {
        echo "  - {$tbl}\n";
    }
    echo str_repeat('-', 65) . "\n\n";
} else {
    echo "✅ All tables have primary keys\n\n";
}

// Summary
echo "=================================================================\n";
echo "SUMMARY\n";
echo "=================================================================\n\n";

$totalIssues = count($issues) + count($nullIssues) + count($missingIndexes) + count($tablesWithoutPK);

if ($totalIssues === 0) {
    echo "✅ No issues found! Database schema is healthy.\n\n";

    if (empty($pendingMigrations)) {
        echo "✅ All migrations are up to date.\n";
        echo "✅ Database is ready for production deployment.\n\n";
    } else {
        echo "⚠️  You have " . count($pendingMigrations) . " pending migrations.\n";
        echo "   Run: php artisan migrate\n\n";
    }
} else {
    echo "⚠️  Found {$totalIssues} issues:\n";
    echo "  - SMALLINT boolean columns: " . count($issues) . "\n";
    echo "  - NULL constraint violations: " . count($nullIssues) . "\n";
    echo "  - Missing indexes: " . count($missingIndexes) . "\n";
    echo "  - Tables without PK: " . count($tablesWithoutPK) . "\n\n";

    echo "Recommendations:\n";
    if (!empty($issues)) {
        echo "  1. Run boolean migration to convert SMALLINT to BOOLEAN\n";
    }
    if (!empty($nullIssues)) {
        echo "  2. Fix NULL values before enforcing NOT NULL constraints\n";
    }
    if (!empty($missingIndexes)) {
        echo "  3. Add indexes to foreign key columns for better performance\n";
    }
    if (!empty($tablesWithoutPK)) {
        echo "  4. Add primary keys to tables\n";
    }
    echo "\n";
}

// Export schema for documentation
echo "=================================================================\n";
echo "SCHEMA EXPORT\n";
echo "=================================================================\n\n";

$schemaFile = "database_schema_" . date('Y-m-d') . ".txt";
$schemaContent = "DATABASE SCHEMA - " . date('Y-m-d H:i:s') . "\n";
$schemaContent .= str_repeat('=', 65) . "\n\n";

foreach ($tables as $table) {
    $tableName = $table->tablename;

    $columns = DB::select("
        SELECT
            column_name,
            data_type,
            character_maximum_length,
            is_nullable,
            column_default
        FROM information_schema.columns
        WHERE table_name = ?
        ORDER BY ordinal_position
    ", [$tableName]);

    $schemaContent .= "Table: {$tableName}\n";
    $schemaContent .= str_repeat('-', 65) . "\n";

    foreach ($columns as $col) {
        $type = $col->data_type;
        if ($col->character_maximum_length) {
            $type .= "({$col->character_maximum_length})";
        }

        $nullable = $col->is_nullable === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $col->column_default ? "DEFAULT {$col->column_default}" : '';

        $schemaContent .= sprintf("  %-30s %-20s %-10s %s\n",
            $col->column_name,
            $type,
            $nullable,
            $default
        );
    }

    $schemaContent .= "\n";
}

file_put_contents($schemaFile, $schemaContent);
echo "✅ Schema exported to: {$schemaFile}\n\n";

echo "=================================================================\n";
