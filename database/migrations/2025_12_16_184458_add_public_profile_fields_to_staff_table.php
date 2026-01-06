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
        Schema::table('staff', function (Blueprint $table) {
            // Public profile fields
            $table->string('slug')->unique()->nullable()->after('name');
            $table->text('bio_long')->nullable()->after('bio'); // Extended bio for profile page
            $table->string('title')->nullable()->after('role'); // Professional title (e.g., "Master Stilista")
            $table->integer('years_experience')->nullable()->after('title');
            $table->json('education')->nullable(); // Education & certifications
            $table->json('achievements')->nullable(); // Awards, competitions, etc.
            $table->json('languages')->nullable(); // Spoken languages
            $table->string('profile_image')->nullable(); // Main profile photo
            $table->string('instagram')->nullable();
            $table->string('facebook')->nullable();
            $table->string('tiktok')->nullable();
            $table->boolean('is_public')->default(true); // Show in public listings
            $table->boolean('accepts_bookings')->default(true); // Can clients book with this staff
            $table->text('booking_note')->nullable(); // Special note for bookings
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn([
                'slug',
                'bio_long',
                'title',
                'years_experience',
                'education',
                'achievements',
                'languages',
                'profile_image',
                'instagram',
                'facebook',
                'tiktok',
                'is_public',
                'accepts_bookings',
                'booking_note',
            ]);
        });
    }
};
