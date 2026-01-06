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
        // Insert sticky navbar setting
        DB::table('system_settings')->insert([
            'key' => 'sticky_navbar',
            'value' => 'true',
            'type' => 'boolean',
            'group' => 'appearance',
            'description' => 'Whether the navbar should stick to the top when scrolling',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove sticky navbar setting
        DB::table('system_settings')->where('key', 'sticky_navbar')->delete();
    }
};
