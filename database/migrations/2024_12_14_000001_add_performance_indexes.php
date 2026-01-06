<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Helper function to check if index exists (PostgreSQL)
        $indexExists = function($table, $indexName) {
            $result = DB::select("SELECT 1 FROM pg_indexes WHERE indexname = ?", [$indexName]);
            return !empty($result);
        };

        // Salons - pretraga po gradu i statusu
        Schema::table('salons', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('salons', 'idx_salons_city')) {
                $table->index('city', 'idx_salons_city');
            }
            if (!$indexExists('salons', 'idx_salons_status')) {
                $table->index('status', 'idx_salons_status');
            }
            if (!$indexExists('salons', 'idx_salons_city_status')) {
                $table->index(['city', 'status'], 'idx_salons_city_status');
            }
        });

        // Appointments - najčešće pretraživani
        Schema::table('appointments', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('appointments', 'idx_appointments_date')) {
                $table->index('date', 'idx_appointments_date');
            }
            if (!$indexExists('appointments', 'idx_appointments_status')) {
                $table->index('status', 'idx_appointments_status');
            }
            if (!$indexExists('appointments', 'idx_appointments_salon_id')) {
                $table->index('salon_id', 'idx_appointments_salon_id');
            }
            if (!$indexExists('appointments', 'idx_appointments_staff_id')) {
                $table->index('staff_id', 'idx_appointments_staff_id');
            }
            if (!$indexExists('appointments', 'idx_appointments_client_id')) {
                $table->index('client_id', 'idx_appointments_client_id');
            }

            // Composite indeksi za složene upite
            if (!$indexExists('appointments', 'idx_appointments_salon_date')) {
                $table->index(['salon_id', 'date'], 'idx_appointments_salon_date');
            }
            if (!$indexExists('appointments', 'idx_appointments_staff_date')) {
                $table->index(['staff_id', 'date'], 'idx_appointments_staff_date');
            }
            if (!$indexExists('appointments', 'idx_appointments_search')) {
                $table->index(['salon_id', 'date', 'status'], 'idx_appointments_search');
            }
        });

        // Reviews
        Schema::table('reviews', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('reviews', 'idx_reviews_salon_id')) {
                $table->index('salon_id', 'idx_reviews_salon_id');
            }
            if (!$indexExists('reviews', 'idx_reviews_client_id')) {
                $table->index('client_id', 'idx_reviews_client_id');
            }
            if (!$indexExists('reviews', 'idx_reviews_created_at')) {
                $table->index('created_at', 'idx_reviews_created_at');
            }
        });

        // Services
        Schema::table('services', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('services', 'idx_services_salon_id')) {
                $table->index('salon_id', 'idx_services_salon_id');
            }
            if (!$indexExists('services', 'idx_services_category')) {
                $table->index('category', 'idx_services_category');
            }
        });

        // Staff
        Schema::table('staff', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('staff', 'idx_staff_salon_id')) {
                $table->index('salon_id', 'idx_staff_salon_id');
            }
            if (!$indexExists('staff', 'idx_staff_user_id')) {
                $table->index('user_id', 'idx_staff_user_id');
            }
        });

        // Favorites
        Schema::table('favorites', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('favorites', 'idx_favorites_user_salon')) {
                $table->index(['user_id', 'salon_id'], 'idx_favorites_user_salon');
            }
        });

        // Notifications
        Schema::table('notifications', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('notifications', 'idx_notifications_recipient_id')) {
                $table->index('recipient_id', 'idx_notifications_recipient_id');
            }
            if (!$indexExists('notifications', 'idx_notifications_read')) {
                $table->index(['recipient_id', 'is_read'], 'idx_notifications_read');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->dropIndex('idx_salons_city');
            $table->dropIndex('idx_salons_status');
            $table->dropIndex('idx_salons_city_status');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['date'], 'idx_appointments_date');
            $table->dropIndex(['status'], 'idx_appointments_status');
            $table->dropIndex(['salon_id'], 'idx_appointments_salon_id');
            $table->dropIndex(['staff_id'], 'idx_appointments_staff_id');
            $table->dropIndex(['client_id'], 'idx_appointments_client_id');
            $table->dropIndex(['salon_id', 'date'], 'idx_appointments_salon_date');
            $table->dropIndex(['staff_id', 'date'], 'idx_appointments_staff_date');
            $table->dropIndex(['salon_id', 'date', 'status'], 'idx_appointments_search');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['salon_id'], 'idx_reviews_salon_id');
            $table->dropIndex(['client_id'], 'idx_reviews_client_id');
            $table->dropIndex(['created_at'], 'idx_reviews_created_at');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex('idx_services_salon_id');
            $table->dropIndex('idx_services_category');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropIndex('idx_staff_salon_id');
            $table->dropIndex('idx_staff_user_id');
        });

        Schema::table('favorites', function (Blueprint $table) {
            $table->dropIndex('idx_favorites_user_salon');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['recipient_id'], 'idx_notifications_recipient_id');
            $table->dropIndex(['recipient_id', 'is_read'], 'idx_notifications_read');
        });
    }
};
