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
     * This migration converts SMALLINT boolean columns to proper BOOLEAN type.
     * Safe for production - preserves all data (0 → false, 1 → true).
     */
    public function up(): void
    {
        // Users table
        // Step 1: Drop existing default, Step 2: Change type, Step 3: Set new default
        DB::statement('ALTER TABLE users ALTER COLUMN is_guest DROP DEFAULT');
        DB::statement('ALTER TABLE users ALTER COLUMN is_guest TYPE BOOLEAN USING (is_guest::integer::boolean)');
        DB::statement('ALTER TABLE users ALTER COLUMN is_guest SET DEFAULT false');
        DB::statement('ALTER TABLE users ALTER COLUMN is_guest SET NOT NULL');

        // Appointments table
        DB::statement('ALTER TABLE appointments ALTER COLUMN is_guest DROP DEFAULT');
        DB::statement('ALTER TABLE appointments ALTER COLUMN is_guest TYPE BOOLEAN USING (is_guest::integer::boolean)');
        DB::statement('ALTER TABLE appointments ALTER COLUMN is_guest SET DEFAULT false');
        DB::statement('ALTER TABLE appointments ALTER COLUMN is_guest SET NOT NULL');

        // Widget Settings table
        DB::statement('ALTER TABLE widget_settings ALTER COLUMN is_active DROP DEFAULT');
        DB::statement('ALTER TABLE widget_settings ALTER COLUMN is_active TYPE BOOLEAN USING (is_active::integer::boolean)');
        DB::statement('ALTER TABLE widget_settings ALTER COLUMN is_active SET DEFAULT true');
        DB::statement('ALTER TABLE widget_settings ALTER COLUMN is_active SET NOT NULL');

        // Salon Settings table
        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_enabled DROP DEFAULT');
        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_enabled TYPE BOOLEAN USING (daily_report_enabled::integer::boolean)');
        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_enabled SET DEFAULT false');

        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_include_staff DROP DEFAULT');
        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_include_staff TYPE BOOLEAN USING (daily_report_include_staff::integer::boolean)');
        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_include_staff SET DEFAULT true');

        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_include_services DROP DEFAULT');
        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_include_services TYPE BOOLEAN USING (daily_report_include_services::integer::boolean)');
        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_include_services SET DEFAULT true');

        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_include_capacity DROP DEFAULT');
        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_include_capacity TYPE BOOLEAN USING (daily_report_include_capacity::integer::boolean)');
        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_include_capacity SET DEFAULT true');

        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_include_cancellations DROP DEFAULT');
        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_include_cancellations TYPE BOOLEAN USING (daily_report_include_cancellations::integer::boolean)');
        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_include_cancellations SET DEFAULT true');

        // Staff table
        DB::statement('ALTER TABLE staff ALTER COLUMN is_active DROP DEFAULT');
        DB::statement('ALTER TABLE staff ALTER COLUMN is_active TYPE BOOLEAN USING (is_active::integer::boolean)');
        DB::statement('ALTER TABLE staff ALTER COLUMN is_active SET DEFAULT true');

        DB::statement('ALTER TABLE staff ALTER COLUMN is_public DROP DEFAULT');
        DB::statement('ALTER TABLE staff ALTER COLUMN is_public TYPE BOOLEAN USING (is_public::integer::boolean)');
        DB::statement('ALTER TABLE staff ALTER COLUMN is_public SET DEFAULT true');

        DB::statement('ALTER TABLE staff ALTER COLUMN accepts_bookings DROP DEFAULT');
        DB::statement('ALTER TABLE staff ALTER COLUMN accepts_bookings TYPE BOOLEAN USING (accepts_bookings::integer::boolean)');
        DB::statement('ALTER TABLE staff ALTER COLUMN accepts_bookings SET DEFAULT true');

        DB::statement('ALTER TABLE staff ALTER COLUMN auto_confirm DROP DEFAULT');
        DB::statement('ALTER TABLE staff ALTER COLUMN auto_confirm TYPE BOOLEAN USING (auto_confirm::integer::boolean)');
        DB::statement('ALTER TABLE staff ALTER COLUMN auto_confirm SET DEFAULT false');

        // Services table
        DB::statement('ALTER TABLE services ALTER COLUMN is_active DROP DEFAULT');
        DB::statement('ALTER TABLE services ALTER COLUMN is_active TYPE BOOLEAN USING (is_active::integer::boolean)');
        DB::statement('ALTER TABLE services ALTER COLUMN is_active SET DEFAULT true');

        // Locations table
        DB::statement('ALTER TABLE locations ALTER COLUMN is_active DROP DEFAULT');
        DB::statement('ALTER TABLE locations ALTER COLUMN is_active TYPE BOOLEAN USING (is_active::integer::boolean)');
        DB::statement('ALTER TABLE locations ALTER COLUMN is_active SET DEFAULT true');

        // Job Ads table
        DB::statement('ALTER TABLE job_ads ALTER COLUMN is_active DROP DEFAULT');
        DB::statement('ALTER TABLE job_ads ALTER COLUMN is_active TYPE BOOLEAN USING (is_active::integer::boolean)');
        DB::statement('ALTER TABLE job_ads ALTER COLUMN is_active SET DEFAULT true');

        // Homepage Categories table
        DB::statement('ALTER TABLE homepage_categories ALTER COLUMN is_enabled DROP DEFAULT');
        DB::statement('ALTER TABLE homepage_categories ALTER COLUMN is_enabled TYPE BOOLEAN USING (is_enabled::integer::boolean)');
        DB::statement('ALTER TABLE homepage_categories ALTER COLUMN is_enabled SET DEFAULT true');

        // Notifications table
        DB::statement('ALTER TABLE notifications ALTER COLUMN is_read DROP DEFAULT');
        DB::statement('ALTER TABLE notifications ALTER COLUMN is_read TYPE BOOLEAN USING (is_read::integer::boolean)');
        DB::statement('ALTER TABLE notifications ALTER COLUMN is_read SET DEFAULT false');

        // Reviews table
        DB::statement('ALTER TABLE reviews ALTER COLUMN is_verified DROP DEFAULT');
        DB::statement('ALTER TABLE reviews ALTER COLUMN is_verified TYPE BOOLEAN USING (is_verified::integer::boolean)');
        DB::statement('ALTER TABLE reviews ALTER COLUMN is_verified SET DEFAULT false');

        // Staff Portfolio table
        DB::statement('ALTER TABLE staff_portfolio ALTER COLUMN is_featured DROP DEFAULT');
        DB::statement('ALTER TABLE staff_portfolio ALTER COLUMN is_featured TYPE BOOLEAN USING (is_featured::integer::boolean)');
        DB::statement('ALTER TABLE staff_portfolio ALTER COLUMN is_featured SET DEFAULT false');

        // User Consents table
        DB::statement('ALTER TABLE user_consents ALTER COLUMN accepted DROP DEFAULT');
        DB::statement('ALTER TABLE user_consents ALTER COLUMN accepted TYPE BOOLEAN USING (accepted::integer::boolean)');
        DB::statement('ALTER TABLE user_consents ALTER COLUMN accepted SET DEFAULT false');

        // Service Images table
        DB::statement('ALTER TABLE service_images ALTER COLUMN is_featured DROP DEFAULT');
        DB::statement('ALTER TABLE service_images ALTER COLUMN is_featured TYPE BOOLEAN USING (is_featured::integer::boolean)');
        DB::statement('ALTER TABLE service_images ALTER COLUMN is_featured SET DEFAULT false');

        // Salon Images table
        DB::statement('ALTER TABLE salon_images ALTER COLUMN is_primary DROP DEFAULT');
        DB::statement('ALTER TABLE salon_images ALTER COLUMN is_primary TYPE BOOLEAN USING (is_primary::integer::boolean)');
        DB::statement('ALTER TABLE salon_images ALTER COLUMN is_primary SET DEFAULT false');

        // Staff Breaks table
        DB::statement('ALTER TABLE staff_breaks ALTER COLUMN is_active DROP DEFAULT');
        DB::statement('ALTER TABLE staff_breaks ALTER COLUMN is_active TYPE BOOLEAN USING (is_active::integer::boolean)');
        DB::statement('ALTER TABLE staff_breaks ALTER COLUMN is_active SET DEFAULT true');

        // Staff Vacations table
        DB::statement('ALTER TABLE staff_vacations ALTER COLUMN is_active DROP DEFAULT');
        DB::statement('ALTER TABLE staff_vacations ALTER COLUMN is_active TYPE BOOLEAN USING (is_active::integer::boolean)');
        DB::statement('ALTER TABLE staff_vacations ALTER COLUMN is_active SET DEFAULT true');

        // Salon Breaks table
        DB::statement('ALTER TABLE salon_breaks ALTER COLUMN is_active DROP DEFAULT');
        DB::statement('ALTER TABLE salon_breaks ALTER COLUMN is_active TYPE BOOLEAN USING (is_active::integer::boolean)');
        DB::statement('ALTER TABLE salon_breaks ALTER COLUMN is_active SET DEFAULT true');

        // Salon Vacations table
        DB::statement('ALTER TABLE salon_vacations ALTER COLUMN is_active DROP DEFAULT');
        DB::statement('ALTER TABLE salon_vacations ALTER COLUMN is_active TYPE BOOLEAN USING (is_active::integer::boolean)');
        DB::statement('ALTER TABLE salon_vacations ALTER COLUMN is_active SET DEFAULT true');
    }

    /**
     * Reverse the migrations.
     *
     * Rollback converts BOOLEAN back to SMALLINT.
     * Safe - preserves all data (false → 0, true → 1).
     */
    public function down(): void
    {
        // Users table
        DB::statement('ALTER TABLE users ALTER COLUMN is_guest TYPE SMALLINT USING is_guest::integer');

        // Appointments table
        DB::statement('ALTER TABLE appointments ALTER COLUMN is_guest TYPE SMALLINT USING is_guest::integer');

        // Widget Settings table
        DB::statement('ALTER TABLE widget_settings ALTER COLUMN is_active TYPE SMALLINT USING is_active::integer');

        // Salon Settings table
        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_enabled TYPE SMALLINT USING daily_report_enabled::integer');
        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_include_staff TYPE SMALLINT USING daily_report_include_staff::integer');
        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_include_services TYPE SMALLINT USING daily_report_include_services::integer');
        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_include_capacity TYPE SMALLINT USING daily_report_include_capacity::integer');
        DB::statement('ALTER TABLE salon_settings ALTER COLUMN daily_report_include_cancellations TYPE SMALLINT USING daily_report_include_cancellations::integer');

        // Staff table
        DB::statement('ALTER TABLE staff ALTER COLUMN is_active TYPE SMALLINT USING is_active::integer');
        DB::statement('ALTER TABLE staff ALTER COLUMN is_public TYPE SMALLINT USING is_public::integer');
        DB::statement('ALTER TABLE staff ALTER COLUMN accepts_bookings TYPE SMALLINT USING accepts_bookings::integer');
        DB::statement('ALTER TABLE staff ALTER COLUMN auto_confirm TYPE SMALLINT USING auto_confirm::integer');

        // Services table
        DB::statement('ALTER TABLE services ALTER COLUMN is_active TYPE SMALLINT USING is_active::integer');

        // Locations table
        DB::statement('ALTER TABLE locations ALTER COLUMN is_active TYPE SMALLINT USING is_active::integer');

        // Job Ads table
        DB::statement('ALTER TABLE job_ads ALTER COLUMN is_active TYPE SMALLINT USING is_active::integer');

        // Homepage Categories table
        DB::statement('ALTER TABLE homepage_categories ALTER COLUMN is_enabled TYPE SMALLINT USING is_enabled::integer');

        // Notifications table
        DB::statement('ALTER TABLE notifications ALTER COLUMN is_read TYPE SMALLINT USING is_read::integer');

        // Reviews table
        DB::statement('ALTER TABLE reviews ALTER COLUMN is_verified TYPE SMALLINT USING is_verified::integer');

        // Staff Portfolio table
        DB::statement('ALTER TABLE staff_portfolio ALTER COLUMN is_featured TYPE SMALLINT USING is_featured::integer');

        // User Consents table
        DB::statement('ALTER TABLE user_consents ALTER COLUMN accepted TYPE SMALLINT USING accepted::integer');

        // Service Images table
        DB::statement('ALTER TABLE service_images ALTER COLUMN is_featured TYPE SMALLINT USING is_featured::integer');

        // Salon Images table
        DB::statement('ALTER TABLE salon_images ALTER COLUMN is_primary TYPE SMALLINT USING is_primary::integer');

        // Staff Breaks table
        DB::statement('ALTER TABLE staff_breaks ALTER COLUMN is_active TYPE SMALLINT USING is_active::integer');

        // Staff Vacations table
        DB::statement('ALTER TABLE staff_vacations ALTER COLUMN is_active TYPE SMALLINT USING is_active::integer');

        // Salon Breaks table
        DB::statement('ALTER TABLE salon_breaks ALTER COLUMN is_active TYPE SMALLINT USING is_active::integer');

        // Salon Vacations table
        DB::statement('ALTER TABLE salon_vacations ALTER COLUMN is_active TYPE SMALLINT USING is_active::integer');
    }
};
