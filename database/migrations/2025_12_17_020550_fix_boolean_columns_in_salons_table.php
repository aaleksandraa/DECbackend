<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change auto_confirm from BOOLEAN to SMALLINT
        DB::statement('ALTER TABLE salons ALTER COLUMN auto_confirm DROP DEFAULT');
        DB::statement('ALTER TABLE salons ALTER COLUMN auto_confirm TYPE SMALLINT USING auto_confirm::integer');
        DB::statement('ALTER TABLE salons ALTER COLUMN auto_confirm SET DEFAULT 0');
        DB::statement('ALTER TABLE salons ADD CONSTRAINT check_auto_confirm CHECK (auto_confirm IN (0, 1))');

        // Ensure show_service_gallery has proper constraint (already SMALLINT from previous migration)
        // Just make sure constraint exists
        DB::statement('ALTER TABLE salons DROP CONSTRAINT IF EXISTS check_show_service_gallery');
        DB::statement('ALTER TABLE salons ADD CONSTRAINT check_show_service_gallery CHECK (show_service_gallery IN (0, 1))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert auto_confirm to BOOLEAN
        DB::statement('ALTER TABLE salons DROP CONSTRAINT IF EXISTS check_auto_confirm');
        DB::statement('ALTER TABLE salons ALTER COLUMN auto_confirm TYPE BOOLEAN USING auto_confirm::boolean');
        DB::statement('ALTER TABLE salons ALTER COLUMN auto_confirm SET DEFAULT false');
    }
};
