<?php

namespace App\Console\Commands;

use App\Jobs\CleanupOldNotifications;
use Illuminate\Console\Command;

class CleanupNotifications extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notifications:cleanup {--days=90 : Number of days to keep notifications}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up old read notifications';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');

        CleanupOldNotifications::dispatch($days);

        $this->info("Cleanup job dispatched. Notifications older than {$days} days will be removed.");

        return Command::SUCCESS;
    }
}
