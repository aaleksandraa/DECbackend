<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if column already exists
        $hasColumn = Schema::hasColumn('salons', 'email_notifications_enabled');

        if (!$hasColumn) {
            Schema::table('salons', function (Blueprint $table) {
                $table->boolean('email_notifications_enabled')->default(true)->after('status');
            });

            // Update existing salons to have email notifications enabled
            DB::table('salons')->update(['email_notifications_enabled' => true]);

            echo "✓ Added email_notifications_enabled column to salons table\n";
            echo "✓ Enabled email notifications for all existing salons\n";
        } else {
            echo "✓ Column email_notifications_enabled already exists\n";

            // Make sure all existing salons have it enabled
            DB::table('salons')
                ->whereNull('email_notifications_enabled')
                ->orWhere('email_notifications_enabled', false)
                ->update(['email_notifications_enabled' => true]);

            echo "✓ Enabled email notifications for salons that had it disabled or NULL\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('salons', 'email_notifications_enabled')) {
            Schema::table('salons', function (Blueprint $table) {
                $table->dropColumn('email_notifications_enabled');
            });
        }
    }
};
