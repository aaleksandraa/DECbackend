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
        Schema::table('staff', function (Blueprint $table) {
            if (!Schema::hasColumn('staff', 'calendar_feed_token')) {
                $table->string('calendar_feed_token', 80)->nullable()->unique()->after('display_order');
            }

            if (!Schema::hasColumn('staff', 'calendar_feed_token_generated_at')) {
                $table->timestamp('calendar_feed_token_generated_at')->nullable()->after('calendar_feed_token');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'calendar_feed_token_generated_at')) {
                $table->dropColumn('calendar_feed_token_generated_at');
            }

            if (Schema::hasColumn('staff', 'calendar_feed_token')) {
                $table->dropUnique('staff_calendar_feed_token_unique');
                $table->dropColumn('calendar_feed_token');
            }
        });
    }
};
