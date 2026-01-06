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
        Schema::table('appointments', function (Blueprint $table) {
            // Add service_ids JSON column for multi-service appointments
            $table->json('service_ids')->nullable()->after('service_id');

            // Make service_id nullable (will use service_ids for multi-service)
            $table->integer('service_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('service_ids');
            $table->integer('service_id')->nullable(false)->change();
        });
    }
};
