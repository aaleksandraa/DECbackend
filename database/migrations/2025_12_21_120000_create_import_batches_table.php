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
        if (Schema::hasTable('import_batches')) {
            // Production-safe: table already exists, do not recreate or alter destructively.
            echo "✓ Table import_batches already exists - skipping create\n";
            return;
        }

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade')
                ->comment('Admin who performed the import');
            $table->string('filename');
            $table->string('file_path')->nullable();
            $table->integer('total_rows')->default(0);
            $table->integer('successful_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->json('errors')->nullable();
            $table->json('service_mapping')->nullable();
            $table->json('user_creation_stats')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
                ->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['salon_id', 'status']);
            $table->index('created_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            // Defensive index creation (idempotent on PostgreSQL).
            DB::statement('CREATE INDEX IF NOT EXISTS import_batches_salon_id_status_index ON import_batches (salon_id, status)');
            DB::statement('CREATE INDEX IF NOT EXISTS import_batches_created_at_index ON import_batches (created_at)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('import_batches')) {
            return;
        }

        // Production-safe rollback: avoid dropping existing import history.
        $hasData = DB::table('import_batches')->exists();
        if ($hasData) {
            echo "✓ Table import_batches contains data - skipping drop to preserve records\n";
            return;
        }

        Schema::drop('import_batches');
    }
};
