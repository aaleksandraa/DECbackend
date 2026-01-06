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
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('salon_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('role');
            $table->text('bio')->nullable();
            $table->string('avatar')->nullable();
            $table->json('working_hours');
            $table->json('specialties')->nullable();
            $table->float('rating')->default(0);
            $table->integer('review_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};