<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Create service_images table to store gallery images for services.
     * Each service can have multiple images showing results/examples.
     */
    public function up(): void
    {
        Schema::create('service_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->string('image_path'); // Path to image file
            $table->string('title')->nullable(); // Optional title/caption
            $table->text('description')->nullable(); // Optional description
            $table->integer('order')->default(0); // Display order
            $table->smallInteger('is_featured')->default(0)->check('is_featured IN (0, 1)'); // Featured image
            $table->timestamps();

            // Index for faster queries
            $table->index(['service_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_images');
    }
};
