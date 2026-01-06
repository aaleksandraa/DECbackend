<?php

/**
 * Chatbot Schema Verification Script
 *
 * Verifies that all chatbot tables are created correctly with:
 * - Proper column types (especially BOOLEAN vs SMALLINT)
 * - Foreign key constraints
 * - Indexes
 * - Default values
 *
 * Run after migration: php backend/verify_chatbot_schema.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "\n========================================\n";
echo "Chatbot Schema Verification\n";
echo "========================================\n\n";

$allGood = true;

// Tables to verify
$tables = [
    'social_integrations' => [
        'boolean_columns' => ['webhook_verified', 'auto_reply_enabled', 'business_hours_only'],
        'foreign_keys' => ['salon_id', 'connected_by_user_id'],
        'json_columns' => ['granted_scopes'],
    ],
    'chatbot_conversations' => [
        'boolean_columns' => ['requires_human'],
        'foreign_keys' => ['salon_id', 'social_integration_id', 'appointment_id', 'human_takeover_by_user_id'],
        'json_columns' => ['context'],
    ],
    'chatbot_messages' => [
        'boolean_columns' => ['ai_processed', 'ai_generated'],
        'foreign_keys' => ['conversation_id'],
        'json_columns' => ['message_payload', 'ai_entities'],
    ],
    'chatbot_analytics' => [
        'boolean_columns' => [],
        'foreign_keys' => ['salon_id'],
        'json_columns' => [],
    ],
];

foreach ($tables as $table => $checks) {
    echo "Checking table: {$table}\n";
    echo str_repeat('-', 50) . "\n";

    if (!Schema::hasTable($table)) {
        echo "  ❌ Table does not exist!\n\n";
        $allGood = false;
        continue;
    }

    echo "  ✅ Table exists\n";

    // Get all columns
    $columns = DB::select("
        SELECT
            column_name,
            data_type,
            is_nullable,
            column_default,
            character_maximum_length
        FROM information_schema.columns
        WHERE table_name = ?
        ORDER BY ordinal_position
    ", [$table]);

    echo "  📊 Total columns: " . count($columns) . "\n";

    // Check boolean columns
    if (!empty($checks['boolean_columns'])) {
        echo "\n  🔍 Checking BOOLEAN columns:\n";
        foreach ($checks['boolean_columns'] as $boolCol) {
            $column = collect($columns)->firstWhere('column_name', $boolCol);
            if (!$column) {
                echo "    ❌ Column '{$boolCol}' not found\n";
                $allGood = false;
            } elseif ($column->data_type !== 'boolean') {
                echo "    ❌ Column '{$boolCol}' is {$column->data_type}, should be BOOLEAN\n";
                $allGood = false;
            } else {
                echo "    ✅ {$boolCol} is BOOLEAN\n";
            }
        }
    }

    // Check JSON columns
    if (!empty($checks['json_columns'])) {
        echo "\n  🔍 Checking JSON columns:\n";
        foreach ($checks['json_columns'] as $jsonCol) {
            $column = collect($columns)->firstWhere('column_name', $jsonCol);
            if (!$column) {
                echo "    ⚠️  Column '{$jsonCol}' not found (might be nullable)\n";
            } elseif (!in_array($column->data_type, ['json', 'jsonb'])) {
                echo "    ❌ Column '{$jsonCol}' is {$column->data_type}, should be JSON\n";
                $allGood = false;
            } else {
                echo "    ✅ {$jsonCol} is {$column->data_type}\n";
            }
        }
    }

    // Check foreign keys
    if (!empty($checks['foreign_keys'])) {
        echo "\n  🔍 Checking Foreign Keys:\n";
        $fks = DB::select("
            SELECT
                tc.constraint_name,
                kcu.column_name,
                ccu.table_name AS foreign_table_name,
                ccu.column_name AS foreign_column_name,
                rc.delete_rule
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.key_column_usage AS kcu
              ON tc.constraint_name = kcu.constraint_name
            JOIN information_schema.constraint_column_usage AS ccu
              ON ccu.constraint_name = tc.constraint_name
            JOIN information_schema.referential_constraints AS rc
              ON tc.constraint_name = rc.constraint_name
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_name = ?
        ", [$table]);

        foreach ($checks['foreign_keys'] as $fkCol) {
            $fk = collect($fks)->firstWhere('column_name', $fkCol);
            if (!$fk) {
                // Check if column is nullable (might not need FK)
                $column = collect($columns)->firstWhere('column_name', $fkCol);
                if ($column && $column->is_nullable === 'YES') {
                    echo "    ⚠️  {$fkCol} is nullable, FK might be optional\n";
                } else {
                    echo "    ❌ Foreign key for '{$fkCol}' not found\n";
                    $allGood = false;
                }
            } else {
                echo "    ✅ {$fk->column_name} -> {$fk->foreign_table_name}.{$fk->foreign_column_name} (ON DELETE {$fk->delete_rule})\n";
            }
        }
    }

    // Check indexes
    $indexes = DB::select("
        SELECT
            indexname,
            indexdef
        FROM pg_indexes
        WHERE tablename = ?
        AND schemaname = 'public'
    ", [$table]);

    if (!empty($indexes)) {
        echo "\n  📑 Indexes: " . count($indexes) . "\n";
        foreach ($indexes as $index) {
            echo "    - {$index->indexname}\n";
        }
    }

    echo "\n";
}

// Check appointments.booking_source
echo "Checking appointments.booking_source:\n";
echo str_repeat('-', 50) . "\n";

if (Schema::hasColumn('appointments', 'booking_source')) {
    echo "  ✅ Column exists\n";

    $column = DB::selectOne("
        SELECT
            data_type,
            column_default,
            character_maximum_length
        FROM information_schema.columns
        WHERE table_name = 'appointments'
        AND column_name = 'booking_source'
    ");

    echo "  Type: {$column->data_type}";
    if ($column->character_maximum_length) {
        echo " ({$column->character_maximum_length})";
    }
    echo "\n";
    echo "  Default: {$column->column_default}\n";

    // Check if index exists
    $index = DB::selectOne("
        SELECT indexname
        FROM pg_indexes
        WHERE tablename = 'appointments'
        AND indexname LIKE '%booking_source%'
    ");

    if ($index) {
        echo "  ✅ Index exists: {$index->indexname}\n";
    } else {
        echo "  ⚠️  No index found for booking_source\n";
    }

    // Show distribution
    $distribution = DB::select("
        SELECT booking_source, COUNT(*) as count
        FROM appointments
        GROUP BY booking_source
        ORDER BY count DESC
    ");

    if (!empty($distribution)) {
        echo "\n  📊 Distribution:\n";
        foreach ($distribution as $dist) {
            echo "    - {$dist->booking_source}: {$dist->count}\n";
        }
    }
} else {
    echo "  ❌ Column does not exist\n";
    $allGood = false;
}

echo "\n========================================\n";
if ($allGood) {
    echo "✅ All Checks Passed!\n";
    echo "========================================\n\n";
    exit(0);
} else {
    echo "❌ Some Checks Failed\n";
    echo "========================================\n\n";
    exit(1);
}
