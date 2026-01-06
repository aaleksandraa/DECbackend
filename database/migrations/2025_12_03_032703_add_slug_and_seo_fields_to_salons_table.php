<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            // Slug for SEO-friendly URLs
            $table->string('slug')->unique()->nullable()->after('name');

            // SEO meta fields
            $table->string('meta_title')->nullable()->after('slug');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->json('meta_keywords')->nullable()->after('meta_description');

            // City slug for city pages
            $table->string('city_slug')->nullable()->after('city');

            // Index for city-based queries
            $table->index('city_slug');
        });

        // Generate slugs for existing salons
        $salons = DB::table('salons')->get();
        foreach ($salons as $salon) {
            $baseSlug = Str::slug($salon->name);
            $slug = $baseSlug;
            $counter = 1;

            // Ensure unique slug
            while (DB::table('salons')->where('slug', $slug)->where('id', '!=', $salon->id)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            $citySlug = Str::slug($salon->city);

            DB::table('salons')->where('id', $salon->id)->update([
                'slug' => $slug,
                'city_slug' => $citySlug,
            ]);
        }

        // Add guest booking fields to appointments if not exists
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'is_guest')) {
                $table->boolean('is_guest')->default(false)->after('client_phone');
            }
            if (!Schema::hasColumn('appointments', 'guest_address')) {
                $table->string('guest_address')->nullable()->after('is_guest');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['is_guest', 'guest_address']);
        });

        Schema::table('salons', function (Blueprint $table) {
            $table->dropIndex(['city_slug']);
            $table->dropColumn(['slug', 'meta_title', 'meta_description', 'meta_keywords', 'city_slug']);
        });
    }
};
