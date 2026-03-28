<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'service_ids')) {
                // Add service_ids JSON column for multi-service appointments
                $table->json('service_ids')->nullable()->after('service_id');
            } else {
                echo "✓ Column appointments.service_ids already exists - skipping add\n";
            }
        });

        if (Schema::hasColumn('appointments', 'service_id')) {
            // Keep existing type, only relax nullability in a DB-safe way.
            DB::statement('ALTER TABLE appointments ALTER COLUMN service_id DROP NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'service_ids')) {
                $table->dropColumn('service_ids');
            }
        });

        if (Schema::hasColumn('appointments', 'service_id')) {
            $hasNullServiceIds = DB::table('appointments')
                ->whereNull('service_id')
                ->exists();

            // Production-safe rollback: do not force NOT NULL if data would violate it.
            if ($hasNullServiceIds) {
                echo "✓ appointments.service_id contains NULL values - skipping NOT NULL restore\n";
                return;
            }

            DB::statement('ALTER TABLE appointments ALTER COLUMN service_id SET NOT NULL');
        }
    }
};
