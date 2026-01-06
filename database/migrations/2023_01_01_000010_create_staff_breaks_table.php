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
        Schema::create('staff_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->enum('type', ['daily', 'weekly', 'specific_date', 'date_range']);
            $table->string('start_time');
            $table->string('end_time');
            $table->json('days')->nullable(); // For weekly breaks
            $table->date('date')->nullable(); // For specific date
            $table->date('start_date')->nullable(); // For date range
            $table->date('end_date')->nullable(); // For date range
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_breaks');
    }
};