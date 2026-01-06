<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds a partial unique index to prevent double booking.
     * The constraint ensures that no two active appointments (pending, confirmed, in_progress)
     * can have the same staff_id, date, and time.
     */
    public function up(): void
    {
        // Create a partial unique index that only applies to active appointments
        // This prevents double booking for the same staff at the same date/time
        // while allowing cancelled/completed appointments to exist at the same slot
        DB::statement("
            CREATE UNIQUE INDEX appointments_no_double_booking
            ON appointments (staff_id, date, time)
            WHERE status IN ('pending', 'confirmed', 'in_progress')
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS appointments_no_double_booking");
    }
};
