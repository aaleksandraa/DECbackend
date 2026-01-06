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
        // For PostgreSQL, explicitly set the column type to boolean
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE user_consents ALTER COLUMN accepted TYPE boolean USING accepted::boolean');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse - boolean is the correct type
    }
};
