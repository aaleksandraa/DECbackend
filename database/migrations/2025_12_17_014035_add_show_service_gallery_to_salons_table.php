<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->smallInteger('show_service_gallery')
                ->default(1)
                ->after('is_active');
        });

        // Add check constraint
        DB::statement('ALTER TABLE salons ADD CONSTRAINT check_show_service_gallery CHECK (show_service_gallery IN (0, 1))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop check constraint first
        DB::statement('ALTER TABLE salons DROP CONSTRAINT IF EXISTS check_show_service_gallery');

        Schema::table('salons', function (Blueprint $table) {
            $table->dropColumn('show_service_gallery');
        });
    }
};
