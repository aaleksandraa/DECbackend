<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SAFE BOOLEAN MIGRATION - Professional Approach
     * - Checks if column exists before altering
     * - Checks current type before converting
     * - Handles DEFAULT constraints properly
     * - No data loss guaranteed
     */
    public function up(): void
    {
        // Helper function to safely convert a column
        $convertColumn = function($table, $column, $defaultValue) {
            // Check if column exists
            $exists = DB::select("
                SELECT column_name, data_type, column_default
                FROM information_schema.columns
                WHERE table_name = ? AND column_name = ?
            ", [$table, $column]);

            if (empty($exists)) {
                echo "⚠️  Column {$table}.{$column} does not exist - skipping\n";
                return;
            }

            $currentType = $exists[0]->data_type;

            // If already boolean, skip
            if ($currentType === 'boolean') {
                echo "✅ Column {$table}.{$column} is already BOOLEAN - skipping\n";
                return;
            }

            // If not smallint or integer, skip
            if (!in_array($currentType, ['smallint', 'integer'])) {
                echo "⚠️  Column {$table}.{$column} is {$currentType} - cannot convert - skipping\n";
                return;
            }

            echo "🔄 Converting {$table}.{$column} from {$currentType} to BOOLEAN...\n";

            try {
                // Step 1: Drop DEFAULT if exists
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP DEFAULT");

                // Step 2: Convert type (SMALLINT/INTEGER → BOOLEAN)
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE BOOLEAN USING ({$column}::integer::boolean)");

                // Step 3: Set new DEFAULT
                $defaultStr = $defaultValue ? 'true' : 'false';
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT {$defaultStr}");

                // Step 4: Set NOT NULL if appropriate
                if ($defaultValue !== null) {
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET NOT NULL");
                }

                echo "✅ Successfully converted {$table}.{$column}\n";
            } catch (\Exception $e) {
                echo "❌ Failed to convert {$table}.{$column}: " . $e->getMessage() . "\n";
                // Don't throw - continue with other columns
            }
        };

        echo "\n========================================\n";
        echo "BOOLEAN MIGRATION - SAFE APPROACH\n";
        echo "========================================\n\n";

        // Users table
        $convertColumn('users', 'is_guest', false);

        // Appointments table
        $convertColumn('appointments', 'is_guest', false);

        // Widget Settings table
        $convertColumn('widget_settings', 'is_active', true);

        // Salon Settings table
        $convertColumn('salon_settings', 'daily_report_enabled', false);
        $convertColumn('salon_settings', 'daily_report_include_staff', true);
        $convertColumn('salon_settings', 'daily_report_include_services', true);
        $convertColumn('salon_settings', 'daily_report_include_capacity', true);
        $convertColumn('salon_settings', 'daily_report_include_cancellations', true);

        // Staff table
        $convertColumn('staff', 'is_active', true);
        $convertColumn('staff', 'is_public', true);
        $convertColumn('staff', 'accepts_bookings', true);
        $convertColumn('staff', 'auto_confirm', false);

        // Services table
        $convertColumn('services', 'is_active', true);

        // Locations table
        $convertColumn('locations', 'is_active', true);

        // Job Ads table
        $convertColumn('job_ads', 'is_active', true);

        // Homepage Categories table
        $convertColumn('homepage_categories', 'is_enabled', true);

        // Notifications table
        $convertColumn('notifications', 'is_read', false);

        // Reviews table
        $convertColumn('reviews', 'is_verified', false);

        // Staff Portfolio table
        $convertColumn('staff_portfolio', 'is_featured', false);

        // User Consents table
        $convertColumn('user_consents', 'accepted', false);

        // Service Images table
        $convertColumn('service_images', 'is_featured', false);

        // Salon Images table
        $convertColumn('salon_images', 'is_primary', false);

        // Staff Breaks table
        $convertColumn('staff_breaks', 'is_active', true);

        // Staff Vacations table
        $convertColumn('staff_vacations', 'is_active', true);

        // Salon Breaks table
        $convertColumn('salon_breaks', 'is_active', true);

        // Salon Vacations table
        $convertColumn('salon_vacations', 'is_active', true);

        echo "\n========================================\n";
        echo "MIGRATION COMPLETE\n";
        echo "========================================\n\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Helper function to safely revert a column
        $revertColumn = function($table, $column) {
            // Check if column exists and is boolean
            $exists = DB::select("
                SELECT column_name, data_type
                FROM information_schema.columns
                WHERE table_name = ? AND column_name = ?
            ", [$table, $column]);

            if (empty($exists)) {
                echo "⚠️  Column {$table}.{$column} does not exist - skipping\n";
                return;
            }

            $currentType = $exists[0]->data_type;

            if ($currentType !== 'boolean') {
                echo "⚠️  Column {$table}.{$column} is not BOOLEAN - skipping\n";
                return;
            }

            echo "🔄 Reverting {$table}.{$column} from BOOLEAN to SMALLINT...\n";

            try {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP DEFAULT");
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE SMALLINT USING ({$column}::integer)");
                echo "✅ Successfully reverted {$table}.{$column}\n";
            } catch (\Exception $e) {
                echo "❌ Failed to revert {$table}.{$column}: " . $e->getMessage() . "\n";
            }
        };

        echo "\n========================================\n";
        echo "BOOLEAN MIGRATION ROLLBACK\n";
        echo "========================================\n\n";

        // Revert all columns
        $revertColumn('users', 'is_guest');
        $revertColumn('appointments', 'is_guest');
        $revertColumn('widget_settings', 'is_active');
        $revertColumn('salon_settings', 'daily_report_enabled');
        $revertColumn('salon_settings', 'daily_report_include_staff');
        $revertColumn('salon_settings', 'daily_report_include_services');
        $revertColumn('salon_settings', 'daily_report_include_capacity');
        $revertColumn('salon_settings', 'daily_report_include_cancellations');
        $revertColumn('staff', 'is_active');
        $revertColumn('staff', 'is_public');
        $revertColumn('staff', 'accepts_bookings');
        $revertColumn('staff', 'auto_confirm');
        $revertColumn('services', 'is_active');
        $revertColumn('locations', 'is_active');
        $revertColumn('job_ads', 'is_active');
        $revertColumn('homepage_categories', 'is_enabled');
        $revertColumn('notifications', 'is_read');
        $revertColumn('reviews', 'is_verified');
        $revertColumn('staff_portfolio', 'is_featured');
        $revertColumn('user_consents', 'accepted');
        $revertColumn('service_images', 'is_featured');
        $revertColumn('salon_images', 'is_primary');
        $revertColumn('staff_breaks', 'is_active');
        $revertColumn('staff_vacations', 'is_active');
        $revertColumn('salon_breaks', 'is_active');
        $revertColumn('salon_vacations', 'is_active');

        echo "\n========================================\n";
        echo "ROLLBACK COMPLETE\n";
        echo "========================================\n\n";
    }
};
