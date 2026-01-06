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
        Schema::create('salon_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained('salons')->onDelete('cascade');

            // Daily report settings
            $table->boolean('daily_report_enabled')->default(false);
            $table->time('daily_report_time')->default('20:00:00');
            $table->string('daily_report_email')->nullable(); // Override salon owner email
            $table->boolean('daily_report_include_staff')->default(true);
            $table->boolean('daily_report_include_services')->default(true);
            $table->boolean('daily_report_include_capacity')->default(true);
            $table->boolean('daily_report_include_cancellations')->default(true);

            // Future settings can be added here
            $table->json('notification_preferences')->nullable();
            $table->json('business_hours_override')->nullable();

            $table->timestamps();

            // Ensure one settings record per salon
            $table->unique('salon_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salon_settings');
    }
};
