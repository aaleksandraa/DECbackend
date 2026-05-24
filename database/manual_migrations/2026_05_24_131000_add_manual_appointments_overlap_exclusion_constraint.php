<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!filter_var(env('BOOKING_ENABLE_OVERLAP_EXCLUSION_CONSTRAINT', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $conflicts = DB::select("
            SELECT a.id AS first_id, b.id AS second_id
            FROM appointments a
            JOIN appointments b
              ON a.staff_id = b.staff_id
             AND a.date = b.date
             AND a.id < b.id
             AND a.deleted_at IS NULL
             AND b.deleted_at IS NULL
             AND a.status IN ('pending', 'confirmed', 'in_progress')
             AND b.status IN ('pending', 'confirmed', 'in_progress')
             AND a.time IS NOT NULL
             AND b.time IS NOT NULL
             AND a.end_time IS NOT NULL
             AND b.end_time IS NOT NULL
             AND tsrange((a.date + a.time::time)::timestamp, (a.date + a.end_time::time)::timestamp, '[)')
                 && tsrange((b.date + b.time::time)::timestamp, (b.date + b.end_time::time)::timestamp, '[)')
            LIMIT 1
        ");

        if (!empty($conflicts)) {
            throw new RuntimeException(
                'appointments_no_overlap was not added because active appointment overlaps exist. Run appointments:audit-booking-safety --json first.'
            );
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement("
            ALTER TABLE appointments
            ADD CONSTRAINT appointments_no_overlap
            EXCLUDE USING gist (
                staff_id WITH =,
                tsrange((date + time::time)::timestamp, (date + end_time::time)::timestamp, '[)') WITH &&
            )
            WHERE (
                deleted_at IS NULL
                AND status IN ('pending', 'confirmed', 'in_progress')
                AND time IS NOT NULL
                AND end_time IS NOT NULL
            )
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_no_overlap');
    }
};
