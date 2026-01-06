<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration fixes any widget_settings records where is_active
     * might have been stored incorrectly due to DB::raw('true') usage.
     */
    public function up(): void
    {
        // For PostgreSQL, ensure all widget_settings have is_active = true
        // This is safe because we want all existing widgets to be active
        DB::statement("UPDATE widget_settings SET is_active = true WHERE is_active IS NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed - data is already correct
    }
};
