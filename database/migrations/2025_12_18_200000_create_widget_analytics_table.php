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
        if (Schema::hasTable('widget_analytics')) {
            return;
        }

        Schema::create('widget_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->nullable()->constrained('salons')->onDelete('cascade');
            $table->foreignId('widget_setting_id')->nullable()->constrained('widget_settings')->onDelete('cascade');
            $table->string('event_type', 50);
            $table->string('referrer_domain')->nullable();
            $table->string('referrer_url')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('salon_id');
            $table->index('widget_setting_id');
            $table->index('event_type');
            $table->index('referrer_domain');
            $table->index('created_at');
            $table->index(['salon_id', 'event_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widget_analytics');
    }
};
