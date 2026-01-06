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
        Schema::create('user_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('consent_type'); // privacy_policy, contact_communication, public_data_display, marketing
            $table->boolean('accepted')->default(false);
            $table->string('version')->default('1.0'); // verzija uslova koje je korisnik prihvatio
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            // Unique constraint - jedan tip pristanka po korisniku
            $table->unique(['user_id', 'consent_type']);

            // Index za brže pretrage
            $table->index(['consent_type', 'accepted']);
            $table->index('accepted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_consents');
    }
};
