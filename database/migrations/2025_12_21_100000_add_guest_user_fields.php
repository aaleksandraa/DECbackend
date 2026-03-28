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
        $hasIsGuest = Schema::hasColumn('users', 'is_guest');
        $hasCreatedVia = Schema::hasColumn('users', 'created_via');

        if (!$hasIsGuest || !$hasCreatedVia) {
            Schema::table('users', function (Blueprint $table) use ($hasIsGuest, $hasCreatedVia) {
                if (!$hasIsGuest) {
                    $table->boolean('is_guest')->default(false)->after('role');
                }

                if (!$hasCreatedVia) {
                    $table->string('created_via')->nullable()->after('is_guest')
                        ->comment('import, widget, admin, registration');
                }
            });
        }

        if (Schema::hasColumn('users', 'is_guest')) {
            // Update existing users - PostgreSQL boolean syntax
            DB::statement("UPDATE users SET is_guest = false WHERE is_guest IS NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasIsGuest = Schema::hasColumn('users', 'is_guest');
        $hasCreatedVia = Schema::hasColumn('users', 'created_via');

        if (!$hasIsGuest && !$hasCreatedVia) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($hasIsGuest, $hasCreatedVia) {
            if ($hasCreatedVia) {
                $table->dropColumn('created_via');
            }

            if ($hasIsGuest) {
                $table->dropColumn('is_guest');
            }
        });
    }
};
