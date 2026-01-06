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
        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique(); // IPv4 or IPv6
            $table->string('reason')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('blocked_at');
            $table->timestamp('expires_at')->nullable();
            $table->integer('block_count')->default(1);
            $table->timestamps();

            $table->index('ip');
            $table->index('blocked_at');
            $table->index('expires_at');
        });

        Schema::create('bot_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45);
            $table->string('user_agent')->nullable();
            $table->string('path')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->boolean('is_bot')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('created_at');

            $table->index('ip');
            $table->index('created_at');
            $table->index(['is_bot', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_requests');
        Schema::dropIfExists('blocked_ips');
    }
};
