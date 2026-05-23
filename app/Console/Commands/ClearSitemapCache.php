<?php

namespace App\Console\Commands;

use App\Support\SitemapCache;
use Illuminate\Console\Command;

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
        SitemapCache::clearAll();

        $this->info('Sitemap cache cleared successfully!');

        return Command::SUCCESS;
    }
}
