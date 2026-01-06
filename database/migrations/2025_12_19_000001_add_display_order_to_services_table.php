<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->integer('display_order')->default(0)->after('category');
        });

        // Add category_order JSON field to salons for custom category ordering
        Schema::table('salons', function (Blueprint $table) {
            $table->json('category_order')->nullable()->after('working_hours');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('display_order');
        });

        Schema::table('salons', function (Blueprint $table) {
            $table->dropColumn('category_order');
        });
    }
};
