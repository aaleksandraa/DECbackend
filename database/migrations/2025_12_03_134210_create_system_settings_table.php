<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, boolean, json, integer
            $table->string('group')->default('general'); // general, analytics, seo, email, etc.
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default settings
        DB::table('system_settings')->insert([
            [
                'key' => 'google_analytics_id',
                'value' => null,
                'type' => 'string',
                'group' => 'analytics',
                'description' => 'Google Analytics Measurement ID (npr. G-XXXXXXXXXX)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'google_analytics_enabled',
                'value' => 'false',
                'type' => 'boolean',
                'group' => 'analytics',
                'description' => 'Da li je Google Analytics omogućen',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'site_name',
                'value' => 'Frizersko-Kozmetički Saloni BiH',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Naziv stranice',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'site_description',
                'value' => 'Pronađite najbolje frizersko-kozmetičke salone u Bosni i Hercegovini.',
                'type' => 'string',
                'group' => 'seo',
                'description' => 'Meta opis stranice',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'contact_email',
                'value' => 'info@frizerski-saloni.ba',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Kontakt email adresa',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
