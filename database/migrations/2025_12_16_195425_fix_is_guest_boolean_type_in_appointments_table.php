<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fix is_guest column to properly handle boolean values in PostgreSQL.
     * This migration ensures the column accepts boolean values correctly.
     */
    public function up(): void
    {
        // For PostgreSQL: Ensure is_guest column properly handles boolean values
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Update any NULL values to false
            DB::statement("UPDATE appointments SET is_guest = false WHERE is_guest IS NULL");

            // Ensure column is properly typed as boolean with default
            DB::statement("ALTER TABLE appointments ALTER COLUMN is_guest TYPE boolean USING is_guest::boolean");
            DB::statement("ALTER TABLE appointments ALTER COLUMN is_guest SET DEFAULT false");
            DB::statement("ALTER TABLE appointments ALTER COLUMN is_guest SET NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse - this is a fix migration
    }
};
