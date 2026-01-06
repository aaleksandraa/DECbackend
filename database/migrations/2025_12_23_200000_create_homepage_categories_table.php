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
        Schema::create('homepage_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('link_type', 50)->default('search'); // 'search', 'url', 'category'
            $table->text('link_value')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('slug');
            $table->index('is_enabled');
            $table->index('display_order');
        });

        // Add homepage category settings to system_settings table
        DB::table('system_settings')->insert([
            [
                'group' => 'homepage',
                'key' => 'categories_enabled',
                'value' => 'false',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group' => 'homepage',
                'key' => 'categories_mobile',
                'value' => 'true',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group' => 'homepage',
                'key' => 'categories_desktop',
                'value' => 'true',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group' => 'homepage',
                'key' => 'categories_layout',
                'value' => 'grid',
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('homepage_categories');

        // Remove settings
        DB::table('system_settings')
            ->where('group', 'homepage')
            ->whereIn('key', ['categories_enabled', 'categories_mobile', 'categories_desktop', 'categories_layout'])
            ->delete();
    }
};
