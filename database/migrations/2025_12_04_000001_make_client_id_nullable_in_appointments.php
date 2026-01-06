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
            // Drop the foreign key constraint first
            $table->dropForeign(['client_id']);

            // Make client_id nullable for manual bookings
            $table->foreignId('client_id')->nullable()->change();

            // Re-add the foreign key constraint with nullable support
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->foreignId('client_id')->nullable(false)->change();
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
