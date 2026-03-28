<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert remaining SMALLINT boolean columns to BOOLEAN
     *
     * Columns to convert:
     * - appointments.is_guest
     * - notifications.is_read
     * - salons.auto_confirm
     * - service_images.is_featured
     */
    public function up(): void
    {
        echo "\n========================================\n";
        echo "Converting Remaining SMALLINT to BOOLEAN\n";
        echo "========================================\n\n";

        $columns = [
            ['table' => 'appointments', 'column' => 'is_guest', 'default' => false],
            ['table' => 'notifications', 'column' => 'is_read', 'default' => false],
            ['table' => 'salons', 'column' => 'auto_confirm', 'default' => false],
            ['table' => 'service_images', 'column' => 'is_featured', 'default' => false],
        ];

        foreach ($columns as $col) {
            $this->convertColumn($col['table'], $col['column'], $col['default']);
        }

        echo "\n========================================\n";
        echo "Migration Complete\n";
        echo "========================================\n\n";
    }

    /**
     * Revert BOOLEAN columns back to SMALLINT
     */
    public function down(): void
    {
        echo "\n========================================\n";
        echo "Reverting BOOLEAN to SMALLINT\n";
        echo "========================================\n\n";

        $columns = [
            'appointments' => 'is_guest',
            'notifications' => 'is_read',
            'salons' => 'auto_confirm',
            'service_images' => 'is_featured',
        ];

        foreach ($columns as $table => $column) {
            $this->revertColumn($table, $column);
        }

        echo "\n========================================\n";
        echo "Rollback Complete\n";
        echo "========================================\n\n";
    }

    private function convertColumn(string $table, string $column, bool $defaultValue): void
    {
        try {
            // Check if column exists
            $exists = DB::select("
                SELECT column_name, data_type
                FROM information_schema.columns
                WHERE table_name = ? AND column_name = ?
            ", [$table, $column]);

            if (empty($exists)) {
                echo "⚠️  {$table}.{$column} does not exist - skipping\n";
                return;
            }

            $currentType = $exists[0]->data_type;

            // If already boolean, skip
            if ($currentType === 'boolean') {
                echo "✅ {$table}.{$column} is already BOOLEAN - skipping\n";
                return;
            }

            // If not smallint, skip
            if ($currentType !== 'smallint') {
                echo "⚠️  {$table}.{$column} is {$currentType} - cannot convert - skipping\n";
                return;
            }

            echo "🔄 Converting {$table}.{$column} from SMALLINT to BOOLEAN...\n";

            // Step 1: Drop DEFAULT constraint
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP DEFAULT");

            // Step 2: Drop legacy SMALLINT CHECK constraints (e.g. column IN (0,1))
            $this->dropPgCheckConstraints($table, $column);

            // Step 3: Convert type using PostgreSQL-safe CASE mapping
            DB::statement(
                "ALTER TABLE {$table} ALTER COLUMN {$column} TYPE BOOLEAN " .
                "USING (CASE WHEN {$column}::text IN ('1', 't', 'true', 'TRUE') THEN true ELSE false END)"
            );

            // Step 4: Set new DEFAULT
            $defaultStr = $defaultValue ? 'true' : 'false';
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT {$defaultStr}");

            // Step 5: Set NOT NULL
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET NOT NULL");

            echo "✅ Successfully converted {$table}.{$column}\n";

        } catch (\Exception $e) {
            echo "❌ Failed to convert {$table}.{$column}: " . $e->getMessage() . "\n";
            throw $e; // Re-throw to stop migration
        }
    }

    private function revertColumn(string $table, string $column): void
    {
        try {
            // Check if column exists and is boolean
            $exists = DB::select("
                SELECT column_name, data_type
                FROM information_schema.columns
                WHERE table_name = ? AND column_name = ?
            ", [$table, $column]);

            if (empty($exists)) {
                echo "⚠️  {$table}.{$column} does not exist - skipping\n";
                return;
            }

            if ($exists[0]->data_type !== 'boolean') {
                echo "⚠️  {$table}.{$column} is not BOOLEAN - skipping\n";
                return;
            }

            echo "🔄 Reverting {$table}.{$column} from BOOLEAN to SMALLINT...\n";

            // Drop DEFAULT, convert type, set DEFAULT to 0
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP DEFAULT");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE SMALLINT USING ({$column}::integer)");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT 0");

            echo "✅ Successfully reverted {$table}.{$column}\n";

        } catch (\Exception $e) {
            echo "❌ Failed to revert {$table}.{$column}: " . $e->getMessage() . "\n";
            // Don't throw - continue with other columns
        }
    }

    /**
     * Drop PostgreSQL CHECK constraints bound to a specific column.
     */
    private function dropPgCheckConstraints(string $table, string $column): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $constraints = DB::select(
            "
            SELECT DISTINCT c.conname
            FROM pg_constraint c
            JOIN pg_class t ON t.oid = c.conrelid
            JOIN pg_namespace n ON n.oid = t.relnamespace
            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(c.conkey)
            WHERE c.contype = 'c'
              AND t.relname = ?
              AND a.attname = ?
            ",
            [$table, $column]
        );

        foreach ($constraints as $constraint) {
            if (!empty($constraint->conname)) {
                DB::statement("ALTER TABLE \"{$table}\" DROP CONSTRAINT IF EXISTS \"{$constraint->conname}\"");
            }
        }
    }
};
