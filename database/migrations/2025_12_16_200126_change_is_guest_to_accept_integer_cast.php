<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Change is_guest column to use integer type with check constraint.
     * This allows Laravel to send 0/1 while PostgreSQL stores as boolean.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Drop the column and recreate with integer type that casts to boolean
            DB::statement("ALTER TABLE appointments DROP COLUMN IF EXISTS is_guest");
            DB::statement("ALTER TABLE appointments ADD COLUMN is_guest SMALLINT NOT NULL DEFAULT 0 CHECK (is_guest IN (0, 1))");

            // Add comment for clarity
            DB::statement("COMMENT ON COLUMN appointments.is_guest IS 'Boolean stored as integer: 0=false (authenticated), 1=true (guest)'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE appointments DROP COLUMN IF EXISTS is_guest");
            DB::statement("ALTER TABLE appointments ADD COLUMN is_guest BOOLEAN NOT NULL DEFAULT false");
        }
    }
};
