<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Change is_read column to use SMALLINT type with CHECK constraint.
     * This allows Laravel to send 0/1 while maintaining boolean semantics.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Drop and recreate as SMALLINT with CHECK constraint
            DB::statement("ALTER TABLE notifications DROP COLUMN IF EXISTS is_read");
            DB::statement("ALTER TABLE notifications ADD COLUMN is_read SMALLINT NOT NULL DEFAULT 0 CHECK (is_read IN (0, 1))");

            // Add comment for clarity
            DB::statement("COMMENT ON COLUMN notifications.is_read IS 'Boolean stored as integer: 0=unread, 1=read'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE notifications DROP COLUMN IF EXISTS is_read");
            DB::statement("ALTER TABLE notifications ADD COLUMN is_read BOOLEAN NOT NULL DEFAULT false");
        }
    }
};
