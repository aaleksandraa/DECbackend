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
     * These indexes significantly improve query performance for:
     * - Calendar views (filtering by salon_id + date)
     * - Staff schedules (filtering by staff_id + date)
     * - Status filtering (pending appointments, etc.)
     * - Client appointment history
     */
    public function up(): void
    {
        // Helper function to check if index exists
        $indexExists = function($table, $indexName) {
            $result = DB::select("SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?", [$table, $indexName]);
            return !empty($result);
        };

        Schema::table('appointments', function (Blueprint $table) use ($indexExists) {
            // Composite index for salon calendar queries (most common)
            if (!$indexExists('appointments', 'idx_appointments_salon_date_status')) {
                $table->index(['salon_id', 'date', 'status'], 'idx_appointments_salon_date_status');
            }

            // Composite index for staff schedule queries
            if (!$indexExists('appointments', 'idx_appointments_staff_date_time')) {
                $table->index(['staff_id', 'date', 'time'], 'idx_appointments_staff_date_time');
            }

            // Index for client appointment history
            if (!$indexExists('appointments', 'idx_appointments_client_date')) {
                $table->index(['client_id', 'date'], 'idx_appointments_client_date');
            }

            // Index for status filtering (pending appointments dashboard)
            if (!$indexExists('appointments', 'idx_appointments_status_date')) {
                $table->index(['status', 'date'], 'idx_appointments_status_date');
            }
        });

        Schema::table('services', function (Blueprint $table) use ($indexExists) {
            // Index for salon services lookup
            if (!$indexExists('services', 'idx_services_salon_active')) {
                $table->index(['salon_id', 'is_active'], 'idx_services_salon_active');
            }
        });

        Schema::table('staff', function (Blueprint $table) use ($indexExists) {
            // Index for salon staff lookup
            if (!$indexExists('staff', 'idx_staff_salon_active')) {
                $table->index(['salon_id', 'is_active'], 'idx_staff_salon_active');
            }
        });

        Schema::table('reviews', function (Blueprint $table) use ($indexExists) {
            // Index for salon reviews
            if (!Schema::hasColumn('reviews', 'salon_id')) {
                return;
            }

            if (!$indexExists('reviews', 'idx_reviews_salon_created')) {
                $table->index(['salon_id', 'created_at'], 'idx_reviews_salon_created');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_appointments_salon_date_status');
            $table->dropIndex('idx_appointments_staff_date_time');
            $table->dropIndex('idx_appointments_client_date');
            $table->dropIndex('idx_appointments_status_date');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex('idx_services_salon_active');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropIndex('idx_staff_salon_active');
        });

        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'salon_id')) {
                $table->dropIndex('idx_reviews_salon_created');
            }
        });
    }
};
