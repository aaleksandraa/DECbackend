<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RestoreDatabase extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'restore:database
                            {backup : Path to backup file (relative to storage/app/backups/database/)}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Restore database from a backup file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $backupFile = $this->argument('backup');

        // If relative path provided, prepend backup directory
        if (!str_starts_with($backupFile, '/') && !str_starts_with($backupFile, storage_path())) {
            $backupFile = storage_path('app/backups/database/' . $backupFile);
        }

        // Check if backup file exists
        if (!file_exists($backupFile)) {
            $this->error("Backup file not found: {$backupFile}");
            return self::FAILURE;
        }

        $this->warn('⚠️  DATABASE RESTORE WARNING ⚠️');
        $this->warn('This will OVERWRITE your current database!');
        $this->warn('All current data will be LOST!');
        $this->newLine();
        $this->info("Backup file: {$backupFile}");
        $this->info("File size: " . $this->formatBytes(filesize($backupFile)));
        $this->info("Created: " . date('Y-m-d H:i:s', filemtime($backupFile)));
        $this->newLine();

        // Confirmation
        if (!$this->option('force')) {
            if (!$this->confirm('Do you want to continue with the restore?', false)) {
                $this->info('Restore cancelled.');
                return self::SUCCESS;
            }
        }

        $this->info('Starting database restore...');

        try {
            $database = config('database.connections.pgsql.database');
            $username = config('database.connections.pgsql.username');
            $password = config('database.connections.pgsql.password');
            $host = config('database.connections.pgsql.host');
            $port = config('database.connections.pgsql.port');

            // Decompress if needed
            $sqlFile = $backupFile;
            $isCompressed = str_ends_with($backupFile, '.gz');

            if ($isCompressed) {
                $this->info('Decompressing backup file...');
                $sqlFile = str_replace('.gz', '', $backupFile);

                $data = gzdecode(file_get_contents($backupFile));
                if ($data === false) {
                    $this->error('Failed to decompress backup file');
                    return self::FAILURE;
                }

                file_put_contents($sqlFile, $data);
            }

            // Set PGPASSWORD environment variable
            putenv("PGPASSWORD={$password}");

            // Drop all tables first
            $this->info('Dropping existing tables...');
            $dropCommand = sprintf(
                'psql -h %s -p %s -U %s -d %s -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;" 2>&1',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($database)
            );

            exec($dropCommand, $dropOutput, $dropReturn);

            if ($dropReturn !== 0) {
                $this->warn('Warning: Could not drop existing tables');
                $this->warn('Output: ' . implode("\n", $dropOutput));
            }

            // Restore database
            $this->info('Restoring database...');
            $restoreCommand = sprintf(
                'psql -h %s -p %s -U %s -d %s -f %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($database),
                escapeshellarg($sqlFile)
            );

            $output = [];
            $returnVar = 0;
            exec($restoreCommand, $output, $returnVar);

            // Clear password from environment
            putenv('PGPASSWORD');

            // Clean up decompressed file if created
            if ($isCompressed && file_exists($sqlFile)) {
                unlink($sqlFile);
            }

            if ($returnVar !== 0) {
                $this->error('Database restore failed!');
                $this->error('Output: ' . implode("\n", $output));
                Log::error('Database restore failed', [
                    'backup_file' => $backupFile,
                    'output' => $output,
                    'return_code' => $returnVar,
                ]);
                return self::FAILURE;
            }

            $this->info('✓ Database restored successfully!');
            $this->newLine();
            $this->warn('IMPORTANT: Run migrations if needed:');
            $this->warn('  php artisan migrate');
            $this->newLine();

            Log::info('Database restored successfully', [
                'backup_file' => $backupFile,
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Restore failed: ' . $e->getMessage());
            Log::error('Database restore exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
