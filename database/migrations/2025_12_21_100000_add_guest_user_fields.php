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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_guest')->default(false)->after('role');
            $table->string('created_via')->nullable()->after('is_guest')
                ->comment('import, widget, admin, registration');
        });

        // Update existing users - PostgreSQL boolean syntax
        DB::statement("UPDATE users SET is_guest = false WHERE is_guest IS NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_guest', 'created_via']);
        });
    }
};
