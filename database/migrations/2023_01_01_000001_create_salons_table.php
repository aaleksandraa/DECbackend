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
        Schema::create('salons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->text('description');
            $table->string('address');
            $table->string('city');
            $table->string('postal_code')->nullable();
            $table->string('country')->default('Bosna i Hercegovina');
            $table->string('phone');
            $table->string('email');
            $table->string('website')->nullable();
            $table->json('working_hours');
            $table->json('location');
            $table->json('target_audience')->nullable();
            $table->json('amenities')->nullable();
            $table->json('social_media')->nullable();
            $table->float('rating')->default(0);
            $table->integer('review_count')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->enum('status', ['pending', 'approved', 'suspended'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salons');
    }
};