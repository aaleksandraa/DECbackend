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

        DB::statement("
            CREATE OR REPLACE FUNCTION appointment_blocking_tsrange(
                appointment_date date,
                start_time text,
                end_time text
            )
            RETURNS tsrange
            LANGUAGE sql
            IMMUTABLE
            STRICT
            AS $$
                SELECT tsrange(
                    appointment_date + make_time(
                        split_part(substring(start_time from 1 for 5), ':', 1)::int,
                        split_part(substring(start_time from 1 for 5), ':', 2)::int,
                        0
                    ),
                    appointment_date + make_time(
                        split_part(substring(end_time from 1 for 5), ':', 1)::int,
                        split_part(substring(end_time from 1 for 5), ':', 2)::int,
                        0
                    ),
                    '[)'
                );
            $$
        ");

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
             AND appointment_blocking_tsrange(a.date, a.time, a.end_time)
                 && appointment_blocking_tsrange(b.date, b.time, b.end_time)
            LIMIT 1
        ");

        if (!empty($conflicts)) {
            throw new RuntimeException(
                'appointments_no_overlap was not added because active appointment overlaps exist. Run appointments:audit-booking-safety --json first.'
            );
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement('
            ALTER TABLE appointments
            ADD CONSTRAINT appointments_no_overlap
            EXCLUDE USING gist (
                staff_id WITH =,
                appointment_blocking_tsrange(date, time, end_time) WITH &&
            )
            WHERE (
                deleted_at IS NULL
                AND status IN (\'pending\', \'confirmed\', \'in_progress\')
                AND time IS NOT NULL
                AND end_time IS NOT NULL
            )
        ');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_no_overlap');
        DB::statement('DROP FUNCTION IF EXISTS appointment_blocking_tsrange(date, text, text)');
    }
};
