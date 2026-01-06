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
        Schema::table('appointments', function (Blueprint $table) {
            // Add source column if it doesn't exist
            if (!Schema::hasColumn('appointments', 'source')) {
                $table->string('source')->default('admin')->after('notes')
                    ->comment('widget, admin, mobile, import');
            }

            // Add import_batch_id for grouping imported appointments
            $table->unsignedBigInteger('import_batch_id')->nullable()->after('source');

            // Add foreign key
            $table->foreign('import_batch_id')
                ->references('id')
                ->on('import_batches')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['import_batch_id']);
            $table->dropColumn('import_batch_id');
        });
    }
};
