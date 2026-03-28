<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration converts SMALLINT boolean columns to proper BOOLEAN type.
     * Safe for production - preserves all data (0 → false, 1 → true).
     */
    public function up(): void
    {
        $columnsToConvert = [
            ['users', 'is_guest', false, true],
            ['appointments', 'is_guest', false, true],
            ['widget_settings', 'is_active', true, true],
            ['salon_settings', 'daily_report_enabled', false, false],
            ['salon_settings', 'daily_report_include_staff', true, false],
            ['salon_settings', 'daily_report_include_services', true, false],
            ['salon_settings', 'daily_report_include_capacity', true, false],
            ['salon_settings', 'daily_report_include_cancellations', true, false],
            ['staff', 'is_active', true, false],
            ['staff', 'is_public', true, false],
            ['staff', 'accepts_bookings', true, false],
            ['staff', 'auto_confirm', false, false],
            ['services', 'is_active', true, false],
            ['locations', 'is_active', true, false],
            ['job_ads', 'is_active', true, false],
            ['homepage_categories', 'is_enabled', true, false],
            ['notifications', 'is_read', false, false],
            ['reviews', 'is_verified', false, false],
            ['staff_portfolio', 'is_featured', false, false],
            ['user_consents', 'accepted', false, false],
            ['service_images', 'is_featured', false, false],
            ['salon_images', 'is_primary', false, false],
            ['staff_breaks', 'is_active', true, false],
            ['staff_vacations', 'is_active', true, false],
            ['salon_breaks', 'is_active', true, false],
            ['salon_vacations', 'is_active', true, false],
        ];

        foreach ($columnsToConvert as [$table, $column, $default, $setNotNull]) {
            $this->convertColumnToBoolean($table, $column, $default, $setNotNull);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Rollback converts BOOLEAN back to SMALLINT.
     * Safe - preserves all data (false → 0, true → 1).
     */
    public function down(): void
    {
        $columnsToRevert = [
            ['users', 'is_guest'],
            ['appointments', 'is_guest'],
            ['widget_settings', 'is_active'],
            ['salon_settings', 'daily_report_enabled'],
            ['salon_settings', 'daily_report_include_staff'],
            ['salon_settings', 'daily_report_include_services'],
            ['salon_settings', 'daily_report_include_capacity'],
            ['salon_settings', 'daily_report_include_cancellations'],
            ['staff', 'is_active'],
            ['staff', 'is_public'],
            ['staff', 'accepts_bookings'],
            ['staff', 'auto_confirm'],
            ['services', 'is_active'],
            ['locations', 'is_active'],
            ['job_ads', 'is_active'],
            ['homepage_categories', 'is_enabled'],
            ['notifications', 'is_read'],
            ['reviews', 'is_verified'],
            ['staff_portfolio', 'is_featured'],
            ['user_consents', 'accepted'],
            ['service_images', 'is_featured'],
            ['salon_images', 'is_primary'],
            ['staff_breaks', 'is_active'],
            ['staff_vacations', 'is_active'],
            ['salon_breaks', 'is_active'],
            ['salon_vacations', 'is_active'],
        ];

        foreach ($columnsToRevert as [$table, $column]) {
            $this->convertColumnToSmallInt($table, $column);
        }
    }

    private function convertColumnToBoolean(string $table, string $column, bool $default, bool $setNotNull): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $tableName = "\"{$table}\"";
        $columnName = "\"{$column}\"";

        DB::statement("ALTER TABLE {$tableName} ALTER COLUMN {$columnName} DROP DEFAULT");

        if (DB::getDriverName() === 'pgsql') {
            $this->dropPgCheckConstraints($table, $column);

            // PostgreSQL-safe conversion that handles 0/1 and legacy textual truthy values.
            DB::statement(
                "ALTER TABLE {$tableName} ALTER COLUMN {$columnName} TYPE BOOLEAN " .
                "USING (CASE WHEN {$columnName}::text IN ('1', 't', 'true', 'TRUE') THEN true ELSE false END)"
            );
        } else {
            DB::statement("ALTER TABLE {$tableName} ALTER COLUMN {$columnName} TYPE BOOLEAN");
        }

        DB::statement("ALTER TABLE {$tableName} ALTER COLUMN {$columnName} SET DEFAULT " . ($default ? 'true' : 'false'));

        if ($setNotNull) {
            DB::statement("ALTER TABLE {$tableName} ALTER COLUMN {$columnName} SET NOT NULL");
        }
    }

    private function convertColumnToSmallInt(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE SMALLINT USING {$column}::integer");
    }

    /**
     * Drop PostgreSQL CHECK constraints bound to a specific column.
     * Old SMALLINT boolean constraints like "column IN (0,1)" can block type conversion.
     */
    private function dropPgCheckConstraints(string $table, string $column): void
    {
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
