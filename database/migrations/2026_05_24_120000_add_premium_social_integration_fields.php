<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salons')) {
            Schema::table('salons', function (Blueprint $table) {
                if (!Schema::hasColumn('salons', 'social_integrations_enabled')) {
                    $table->boolean('social_integrations_enabled')
                        ->default(false)
                        ->after('chatbot_enabled');
                }
            });
        }

        if (Schema::hasTable('social_integrations')) {
            Schema::table('social_integrations', function (Blueprint $table) {
                if (!Schema::hasColumn('social_integrations', 'connection_mode')) {
                    $table->string('connection_mode', 30)
                        ->default('facebook_page')
                        ->after('platform')
                        ->comment('facebook_page, instagram_only');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('social_integrations')) {
            Schema::table('social_integrations', function (Blueprint $table) {
                if (Schema::hasColumn('social_integrations', 'connection_mode')) {
                    $table->dropColumn('connection_mode');
                }
            });
        }

        if (Schema::hasTable('salons')) {
            Schema::table('salons', function (Blueprint $table) {
                if (Schema::hasColumn('salons', 'social_integrations_enabled')) {
                    $table->dropColumn('social_integrations_enabled');
                }
            });
        }
    }
};
