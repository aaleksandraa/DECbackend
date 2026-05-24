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
        if (!Schema::hasTable('appointments') || Schema::hasColumn('appointments', 'review_request_sent_at')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('review_request_sent_at')->nullable()->after('idempotency_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('appointments') || !Schema::hasColumn('appointments', 'review_request_sent_at')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('review_request_sent_at');
        });
    }
};
