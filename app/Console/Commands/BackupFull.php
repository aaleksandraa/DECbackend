<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BackupFull extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backup:full
                            {--retention=90 : Number of days to keep full backups}';

    /**
     * The console command description.
     */
    protected $description = 'Create a complete backup (database + files)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting FULL backup (database + files)...');
        $this->newLine();

        $startTime = microtime(true);
        $errors = [];

        try {
            // 1. Backup Database
            $this->info('Step 1/2: Backing up database...');
            $dbResult = Artisan::call('backup:database', [
                '--retention' => $this->option('retention'),
                '--compress' => true,
            ]);

            if ($dbResult !== 0) {
                $errors[] = 'Database backup failed';
                $this->error('✗ Database backup failed');
            } else {
                $this->info('✓ Database backup completed');
            }

            $this->newLine();

            // 2. Backup Files
            $this->info('Step 2/2: Backing up files...');
            $filesResult = Artisan::call('backup:files', [
                '--retention' => $this->option('retention'),
            ]);

            if ($filesResult !== 0) {
                $errors[] = 'Files backup failed';
                $this->error('✗ Files backup failed');
            } else {
                $this->info('✓ Files backup completed');
            }

            $this->newLine();

            // Summary
            $duration = round(microtime(true) - $startTime, 2);

            if (empty($errors)) {
                $this->info("✓ FULL backup completed successfully in {$duration}s");

                Log::info('Full backup completed', [
                    'duration' => $duration,
                    'retention_days' => $this->option('retention'),
                ]);

                return self::SUCCESS;
            } else {
                $this->error('✗ FULL backup completed with errors:');
                foreach ($errors as $error) {
                    $this->error("  - {$error}");
                }

                Log::error('Full backup completed with errors', [
                    'errors' => $errors,
                    'duration' => $duration,
                ]);

                return self::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('Full backup failed: ' . $e->getMessage());
            Log::error('Full backup exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }
}
