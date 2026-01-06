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
        Schema::create('job_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('company_name'); // Salon/company name
            $table->string('position_title'); // e.g., "Frizer", "Kozmetičar", "Pripravnik"
            $table->text('description');
            $table->enum('gender_requirement', ['male', 'female', 'any'])->default('any');
            $table->string('contact_email');
            $table->string('contact_phone')->nullable();
            $table->string('city')->nullable();
            $table->date('deadline')->nullable(); // Application deadline
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_ads');
    }
};
