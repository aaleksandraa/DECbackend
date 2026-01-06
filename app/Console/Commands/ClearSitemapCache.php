<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearSitemapCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all sitemap caches';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Cache::forget('sitemap_index');
        Cache::forget('sitemap_static');
        Cache::forget('sitemap_cities');
        Cache::forget('sitemap_salons');
        Cache::forget('sitemap_staff');
        Cache::forget('sitemap_services');

        $this->info('Sitemap cache cleared successfully!');

        return Command::SUCCESS;
    }
}
