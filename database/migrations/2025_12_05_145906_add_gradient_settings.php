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
        // Add gradient theme settings
        $gradientPresets = json_encode([
            [
                'id' => 'beauty-rose',
                'name' => 'Beauty Rose',
                'from' => '#f43f5e',
                'via' => '#ec4899',
                'to' => '#a855f7',
                'direction' => 'br'
            ],
            [
                'id' => 'sunset-glow',
                'name' => 'Sunset Glow',
                'from' => '#f97316',
                'via' => '#f43f5e',
                'to' => '#ec4899',
                'direction' => 'r'
            ],
            [
                'id' => 'ocean-breeze',
                'name' => 'Ocean Breeze',
                'from' => '#06b6d4',
                'via' => '#3b82f6',
                'to' => '#8b5cf6',
                'direction' => 'r'
            ],
            [
                'id' => 'forest-mint',
                'name' => 'Forest Mint',
                'from' => '#10b981',
                'via' => '#14b8a6',
                'to' => '#06b6d4',
                'direction' => 'r'
            ],
            [
                'id' => 'royal-purple',
                'name' => 'Royal Purple',
                'from' => '#7c3aed',
                'via' => '#a855f7',
                'to' => '#ec4899',
                'direction' => 'r'
            ],
            [
                'id' => 'golden-hour',
                'name' => 'Golden Hour',
                'from' => '#f59e0b',
                'via' => '#f97316',
                'to' => '#ef4444',
                'direction' => 'r'
            ],
            [
                'id' => 'midnight-aurora',
                'name' => 'Midnight Aurora',
                'from' => '#1e3a8a',
                'via' => '#7c3aed',
                'to' => '#ec4899',
                'direction' => 'r'
            ],
            [
                'id' => 'cherry-blossom',
                'name' => 'Cherry Blossom',
                'from' => '#fda4af',
                'via' => '#f472b6',
                'to' => '#e879f9',
                'direction' => 'r'
            ]
        ]);

        // Insert default gradient setting - Beauty Rose
        DB::table('system_settings')->insert([
            'key' => 'homepage_gradient',
            'value' => json_encode([
                'preset' => 'beauty-rose',
                'from' => '#f43f5e',
                'via' => '#ec4899',
                'to' => '#a855f7',
                'direction' => 'br',
                'custom' => false
            ]),
            'type' => 'json',
            'group' => 'appearance',
            'description' => 'Gradient boje za hero sekciju na početnoj stranici',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert gradient presets
        DB::table('system_settings')->insert([
            'key' => 'gradient_presets',
            'value' => $gradientPresets,
            'type' => 'json',
            'group' => 'appearance',
            'description' => 'Predefinirane gradient teme',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', ['homepage_gradient', 'gradient_presets'])->delete();
    }
};
