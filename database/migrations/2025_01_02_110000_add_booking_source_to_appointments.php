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
     * ONLY MODIFICATION TO EXISTING TABLES
     * Adds booking_source column to track where appointment came from
     *
     * SAFE FOR PRODUCTION:
     * - Checks if column already exists
     * - Non-breaking change (has default value)
     * - Migrates data from existing 'source' column if present
     * - Rollback support
     */
    public function up(): void
    {
        echo "\n========================================\n";
        echo "Adding booking_source to appointments\n";
        echo "========================================\n\n";

        // Check if column already exists
        $exists = DB::select("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_name = 'appointments'
            AND column_name = 'booking_source'
        ");

        if (!empty($exists)) {
            echo "⚠️  Column booking_source already exists - skipping\n";
            echo "\n========================================\n";
            echo "Migration Complete (No Changes)\n";
            echo "========================================\n\n";
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('booking_source', 20)
                ->default('web')
                ->after('status')
                ->comment('Source: web, widget, chatbot, manual, admin, import');

            $table->index('booking_source', 'appointments_booking_source_idx');
        });

        echo "✅ Added booking_source column\n";

        // Update existing records based on current 'source' column if it exists
        if (Schema::hasColumn('appointments', 'source')) {
            echo "🔄 Migrating data from 'source' column...\n";

            $updated = DB::statement("
                UPDATE appointments
                SET booking_source = CASE
                    WHEN source = 'widget' THEN 'widget'
                    WHEN source = 'admin' THEN 'admin'
                    WHEN source = 'import' THEN 'import'
                    ELSE 'web'
                END
                WHERE booking_source = 'web'
            ");

            echo "✅ Migrated data from 'source' column\n";

            // Count records by source
            $counts = DB::select("
                SELECT booking_source, COUNT(*) as count
                FROM appointments
                GROUP BY booking_source
                ORDER BY count DESC
            ");

            echo "\nBooking source distribution:\n";
            foreach ($counts as $count) {
                echo "  - {$count->booking_source}: {$count->count}\n";
            }
        } else {
            echo "ℹ️  No 'source' column found - all appointments set to 'web'\n";
        }

        echo "\n========================================\n";
        echo "Migration Complete\n";
        echo "========================================\n\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        echo "\n========================================\n";
        echo "Removing booking_source from appointments\n";
        echo "========================================\n\n";

        if (!Schema::hasColumn('appointments', 'booking_source')) {
            echo "⚠️  Column booking_source does not exist - skipping\n";
            echo "\n========================================\n";
            echo "Rollback Complete (No Changes)\n";
            echo "========================================\n\n";
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_booking_source_idx');
            $table->dropColumn('booking_source');
        });

        echo "✅ Removed booking_source column\n";

        echo "\n========================================\n";
        echo "Rollback Complete\n";
        echo "========================================\n\n";
    }
};
