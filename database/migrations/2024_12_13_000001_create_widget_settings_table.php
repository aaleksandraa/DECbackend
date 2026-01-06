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
        Schema::create('widget_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained('salons')->onDelete('cascade');
            $table->string('api_key', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->json('allowed_domains')->nullable(); // Array of allowed domains
            $table->json('theme')->nullable(); // Theme customization
            $table->json('settings')->nullable(); // Additional settings
            $table->timestamp('last_used_at')->nullable();
            $table->integer('total_bookings')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('api_key');
            $table->index('salon_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widget_settings');
    }
};
