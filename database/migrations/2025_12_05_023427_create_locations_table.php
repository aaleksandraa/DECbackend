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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Naziv mjesta (Sarajevo, Mostar, etc.)
            $table->string('city_slug')->unique(); // URL-friendly slug
            $table->string('postal_code', 10)->nullable(); // Poštanski broj
            $table->enum('entity', ['FBiH', 'RS', 'BD'])->default('FBiH'); // Entitet
            $table->string('canton')->nullable(); // Kanton (samo za FBiH)
            $table->string('region')->nullable(); // Regija (za RS)
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('population')->nullable(); // Za sortiranje po veličini
            $table->timestamps();

            $table->index(['entity', 'canton']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
