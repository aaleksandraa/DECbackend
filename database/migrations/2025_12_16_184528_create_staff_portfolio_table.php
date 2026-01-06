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
        Schema::create('staff_portfolio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->string('image_url'); // URL or path to portfolio image
            $table->string('title')->nullable(); // Title/description of work
            $table->text('description')->nullable(); // Detailed description
            $table->string('category')->nullable(); // e.g., "Šišanje", "Farbanje", "Balayage"
            $table->json('tags')->nullable(); // Tags for filtering (e.g., ["kratka kosa", "plava"])
            $table->integer('order')->default(0); // Display order
            $table->boolean('is_featured')->default(false); // Featured work
            $table->timestamps();

            $table->index('staff_id');
            $table->index('is_featured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_portfolio');
    }
};
