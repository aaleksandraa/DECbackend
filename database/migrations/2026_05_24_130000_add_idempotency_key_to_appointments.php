<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'idempotency_key')) {
                $table->string('idempotency_key', 100)->nullable()->after('booking_source');
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->unique(
                ['salon_id', 'booking_source', 'idempotency_key'],
                'appointments_idempotency_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('appointments_idempotency_unique');
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'idempotency_key')) {
                $table->dropColumn('idempotency_key');
            }
        });
    }
};
