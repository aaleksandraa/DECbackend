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
        if (!Schema::hasTable('homepage_categories')) {
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
        } else {
            echo "✓ Table homepage_categories already exists - skipping create\n";
        }

        // Add/update homepage category settings in system_settings table
        $settings = [
            [
                'group' => 'homepage',
                'key' => 'categories_enabled',
                'value' => 'false',
                'type' => 'boolean',
                'updated_at' => now(),
            ],
            [
                'group' => 'homepage',
                'key' => 'categories_mobile',
                'value' => 'true',
                'type' => 'boolean',
                'updated_at' => now(),
            ],
            [
                'group' => 'homepage',
                'key' => 'categories_desktop',
                'value' => 'true',
                'type' => 'boolean',
                'updated_at' => now(),
            ],
            [
                'group' => 'homepage',
                'key' => 'categories_layout',
                'value' => 'grid',
                'type' => 'string',
                'updated_at' => now(),
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    'group' => $setting['group'],
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                    'updated_at' => $setting['updated_at'],
                    'created_at' => now(),
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('homepage_categories') && DB::table('homepage_categories')->exists()) {
            echo "✓ Table homepage_categories contains data - skipping drop to preserve records\n";
        } else {
            Schema::dropIfExists('homepage_categories');
        }

        // Remove settings
        DB::table('system_settings')
            ->where('group', 'homepage')
            ->whereIn('key', ['categories_enabled', 'categories_mobile', 'categories_desktop', 'categories_layout'])
            ->delete();
    }
};
