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
        Schema::table('salons', function (Blueprint $table) {
            // Add booking slot interval in minutes (15, 30, 45, 60)
            // Default is 30 minutes
            $table->integer('booking_slot_interval')->default(30)->after('auto_confirm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->dropColumn('booking_slot_interval');
        });
    }
};
