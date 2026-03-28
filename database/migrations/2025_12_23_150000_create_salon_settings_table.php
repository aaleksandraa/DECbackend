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
        if (Schema::hasTable('salon_settings')) {
            echo "✓ Table salon_settings already exists - ensuring required columns\n";
            $this->ensureExistingTableShape();
            return;
        }

        Schema::create('salon_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained('salons')->onDelete('cascade');

            // Daily report settings
            $table->boolean('daily_report_enabled')->default(false);
            $table->time('daily_report_time')->default('20:00:00');
            $table->string('daily_report_email')->nullable(); // Override salon owner email
            $table->boolean('daily_report_include_staff')->default(true);
            $table->boolean('daily_report_include_services')->default(true);
            $table->boolean('daily_report_include_capacity')->default(true);
            $table->boolean('daily_report_include_cancellations')->default(true);

            // Future settings can be added here
            $table->json('notification_preferences')->nullable();
            $table->json('business_hours_override')->nullable();

            $table->timestamps();

            // Ensure one settings record per salon
            $table->unique('salon_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('salon_settings')) {
            return;
        }

        // Production-safe rollback: preserve existing settings data.
        if (DB::table('salon_settings')->exists()) {
            echo "✓ Table salon_settings contains data - skipping drop to preserve records\n";
            return;
        }

        Schema::drop('salon_settings');
    }

    private function ensureExistingTableShape(): void
    {
        $hasDailyReportEnabled = Schema::hasColumn('salon_settings', 'daily_report_enabled');
        $hasDailyReportTime = Schema::hasColumn('salon_settings', 'daily_report_time');
        $hasDailyReportEmail = Schema::hasColumn('salon_settings', 'daily_report_email');
        $hasIncludeStaff = Schema::hasColumn('salon_settings', 'daily_report_include_staff');
        $hasIncludeServices = Schema::hasColumn('salon_settings', 'daily_report_include_services');
        $hasIncludeCapacity = Schema::hasColumn('salon_settings', 'daily_report_include_capacity');
        $hasIncludeCancellations = Schema::hasColumn('salon_settings', 'daily_report_include_cancellations');
        $hasNotificationPreferences = Schema::hasColumn('salon_settings', 'notification_preferences');
        $hasBusinessHoursOverride = Schema::hasColumn('salon_settings', 'business_hours_override');
        $hasCreatedAt = Schema::hasColumn('salon_settings', 'created_at');
        $hasUpdatedAt = Schema::hasColumn('salon_settings', 'updated_at');

        Schema::table('salon_settings', function (Blueprint $table) use (
            $hasDailyReportEnabled,
            $hasDailyReportTime,
            $hasDailyReportEmail,
            $hasIncludeStaff,
            $hasIncludeServices,
            $hasIncludeCapacity,
            $hasIncludeCancellations,
            $hasNotificationPreferences,
            $hasBusinessHoursOverride,
            $hasCreatedAt,
            $hasUpdatedAt
        ) {
            if (!$hasDailyReportEnabled) {
                $table->boolean('daily_report_enabled')->default(false);
            }

            if (!$hasDailyReportTime) {
                $table->time('daily_report_time')->default('20:00:00');
            }

            if (!$hasDailyReportEmail) {
                $table->string('daily_report_email')->nullable();
            }

            if (!$hasIncludeStaff) {
                $table->boolean('daily_report_include_staff')->default(true);
            }

            if (!$hasIncludeServices) {
                $table->boolean('daily_report_include_services')->default(true);
            }

            if (!$hasIncludeCapacity) {
                $table->boolean('daily_report_include_capacity')->default(true);
            }

            if (!$hasIncludeCancellations) {
                $table->boolean('daily_report_include_cancellations')->default(true);
            }

            if (!$hasNotificationPreferences) {
                $table->json('notification_preferences')->nullable();
            }

            if (!$hasBusinessHoursOverride) {
                $table->json('business_hours_override')->nullable();
            }

            if (!$hasCreatedAt && !$hasUpdatedAt) {
                $table->timestamps();
            } else {
                if (!$hasCreatedAt) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!$hasUpdatedAt) {
                    $table->timestamp('updated_at')->nullable();
                }
            }
        });

        if (Schema::hasColumn('salon_settings', 'salon_id') &&
            !$this->uniqueConstraintExists('salon_settings', 'salon_settings_salon_id_unique')) {
            if ($this->hasDuplicateSalonIds()) {
                echo "⚠ salon_settings has duplicate salon_id values - skipping unique index creation\n";
                return;
            }

            Schema::table('salon_settings', function (Blueprint $table) {
                $table->unique('salon_id');
            });
        }
    }

    private function uniqueConstraintExists(string $table, string $constraintName): bool
    {
        if (DB::getDriverName() === 'pgsql') {
            $result = DB::selectOne(
                "
                SELECT 1
                FROM pg_constraint c
                JOIN pg_class t ON t.oid = c.conrelid
                WHERE c.contype = 'u'
                  AND t.relname = ?
                  AND c.conname = ?
                LIMIT 1
                ",
                [$table, $constraintName]
            );

            return (bool) $result;
        }

        $database = DB::getDatabaseName();
        $result = DB::selectOne(
            "
            SELECT 1
            FROM information_schema.table_constraints
            WHERE table_schema = ?
              AND table_name = ?
              AND constraint_name = ?
              AND constraint_type = 'UNIQUE'
            LIMIT 1
            ",
            [$database, $table, $constraintName]
        );

        return (bool) $result;
    }

    private function hasDuplicateSalonIds(): bool
    {
        $duplicate = DB::table('salon_settings')
            ->select('salon_id', DB::raw('COUNT(*) as aggregate_count'))
            ->whereNotNull('salon_id')
            ->groupBy('salon_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->first();

        return $duplicate !== null;
    }
};
