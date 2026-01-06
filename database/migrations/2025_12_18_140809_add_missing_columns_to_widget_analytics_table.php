<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('widget_analytics', function (Blueprint $table) {
            // Add widget_setting_id if it doesn't exist
            if (!Schema::hasColumn('widget_analytics', 'widget_setting_id')) {
                $table->foreignId('widget_setting_id')->nullable()->after('salon_id')->constrained('widget_settings')->onDelete('cascade');
                $table->index('widget_setting_id');
            }

            // Add referrer_url if it doesn't exist
            if (!Schema::hasColumn('widget_analytics', 'referrer_url')) {
                $table->string('referrer_url')->nullable()->after('referrer_domain');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('widget_analytics', function (Blueprint $table) {
            // Drop foreign key and column
            if (Schema::hasColumn('widget_analytics', 'widget_setting_id')) {
                $table->dropForeign(['widget_setting_id']);
                $table->dropColumn('widget_setting_id');
            }

            // Drop referrer_url column
            if (Schema::hasColumn('widget_analytics', 'referrer_url')) {
                $table->dropColumn('referrer_url');
            }
        });
    }
};
