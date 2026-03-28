<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS appointments_no_double_booking');

        DB::statement("
            CREATE UNIQUE INDEX appointments_no_double_booking
            ON appointments (staff_id, date, time)
            WHERE deleted_at IS NULL
              AND status IN ('pending', 'confirmed', 'in_progress')
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS appointments_no_double_booking');

        DB::statement("
            CREATE UNIQUE INDEX appointments_no_double_booking
            ON appointments (staff_id, date, time)
            WHERE status IN ('pending', 'confirmed', 'in_progress')
        ");
    }
};
