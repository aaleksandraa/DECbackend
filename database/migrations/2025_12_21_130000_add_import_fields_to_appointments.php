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
        if (!Schema::hasTable('appointments')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'source')) {
                $table->string('source')->default('admin')->after('notes')
                    ->comment('widget, admin, mobile, import');
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'import_batch_id')) {
                $table->unsignedBigInteger('import_batch_id')->nullable()->after('source');
            }
        });

        if (Schema::hasTable('import_batches') &&
            Schema::hasColumn('appointments', 'import_batch_id') &&
            !$this->foreignKeyExists('appointments', 'appointments_import_batch_id_foreign')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->foreign('import_batch_id')
                    ->references('id')
                    ->on('import_batches')
                    ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if ($this->foreignKeyExists('appointments', 'appointments_import_batch_id_foreign')) {
                $table->dropForeign('appointments_import_batch_id_foreign');
            }

            if (Schema::hasColumn('appointments', 'import_batch_id')) {
                $table->dropColumn('import_batch_id');
            }
        });
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $result = DB::selectOne(
                "
                SELECT 1
                FROM pg_constraint c
                JOIN pg_class t ON t.oid = c.conrelid
                WHERE c.contype = 'f'
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
              AND constraint_type = 'FOREIGN KEY'
            LIMIT 1
            ",
            [$database, $table, $constraintName]
        );

        return (bool) $result;
    }
};
