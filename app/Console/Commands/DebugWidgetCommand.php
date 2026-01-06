<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WidgetSetting;
use App\Models\Salon;
use Illuminate\Support\Facades\DB;

class DebugWidgetCommand extends Command
{
    protected $signature = 'widget:debug {--key= : API key to check} {--salon= : Salon slug to check}';
    protected $description = 'Debug widget settings and API keys';

    public function handle()
    {
        $this->info('=== Widget Debug ===');
        $this->newLine();

        // Check specific API key
        $key = $this->option('key');
        if ($key) {
            $this->info("Checking API key: {$key}");
            $widget = WidgetSetting::where('api_key', $key)->first();

            if ($widget) {
                $this->info("✓ Found! Widget ID: {$widget->id}, Salon ID: {$widget->salon_id}");
                $this->info("  is_active: " . ($widget->is_active ? 'true' : 'false'));
            } else {
                $this->error("✗ API key not found in database!");
            }
            $this->newLine();
        }

        // Check specific salon
        $salonSlug = $this->option('salon');
        if ($salonSlug) {
            $this->info("Checking salon: {$salonSlug}");
            $salon = Salon::where('slug', $salonSlug)->first();

            if ($salon) {
                $this->info("✓ Salon found: {$salon->name} (ID: {$salon->id})");

                $widget = WidgetSetting::where('salon_id', $salon->id)->first();
                if ($widget) {
                    $this->info("✓ Widget exists!");
                    $this->info("  API Key: {$widget->api_key}");
                    $this->info("  is_active: " . ($widget->is_active ? 'true' : 'false'));
                } else {
                    $this->warn("✗ No widget for this salon! Need to generate API key in admin panel.");
                }
            } else {
                $this->error("✗ Salon not found!");
            }
            $this->newLine();
        }

        // List all widgets
        $this->info('=== All Widgets ===');
        $widgets = WidgetSetting::with('salon:id,name,slug')->get();

        if ($widgets->isEmpty()) {
            $this->warn('No widgets found in database!');
        } else {
            $this->table(
                ['ID', 'Salon', 'API Key', 'Active', 'Bookings', 'Last Used'],
                $widgets->map(function($w) {
                    return [
                        $w->id,
                        $w->salon ? $w->salon->name : 'N/A',
                        substr($w->api_key, 0, 25) . '...',
                        $w->is_active ? 'Yes' : 'No',
                        $w->total_bookings,
                        $w->last_used_at ?? 'Never',
                    ];
                })
            );
        }

        return 0;
    }
}
