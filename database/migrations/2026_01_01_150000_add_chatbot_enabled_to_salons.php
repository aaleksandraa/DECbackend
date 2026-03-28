<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('salons')) {
            return;
        }

        Schema::table('salons', function (Blueprint $table) {
            if (!Schema::hasColumn('salons', 'chatbot_enabled')) {
                $table->boolean('chatbot_enabled')->default(false)->after('email');
            } else {
                echo "✓ Column salons.chatbot_enabled already exists - skipping add\n";
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('salons')) {
            return;
        }

        Schema::table('salons', function (Blueprint $table) {
            if (Schema::hasColumn('salons', 'chatbot_enabled')) {
                $table->dropColumn('chatbot_enabled');
            }
        });
    }
};
