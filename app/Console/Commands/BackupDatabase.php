<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backup:database
                            {--retention=30 : Number of days to keep backups}
                            {--compress : Compress the backup file}';

    /**
     * The console command description.
     */
    protected $description = 'Create a PostgreSQL database backup';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting database backup...');

        try {
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $database = config('database.connections.pgsql.database');
            $username = config('database.connections.pgsql.username');
            $password = config('database.connections.pgsql.password');
            $host = config('database.connections.pgsql.host');
            $port = config('database.connections.pgsql.port');

            // Create backup directory if it doesn't exist
            $backupDir = storage_path('app/backups/database');
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $filename = "db_backup_{$timestamp}.sql";
            $filepath = $backupDir . '/' . $filename;

            // Set PGPASSWORD environment variable for pg_dump
            putenv("PGPASSWORD={$password}");

            // Build pg_dump command
            $command = sprintf(
                'pg_dump -h %s -p %s -U %s -d %s --no-owner --no-acl -F p -f %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($database),
                escapeshellarg($filepath)
            );

            // Execute backup
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);

            // Clear password from environment
            putenv('PGPASSWORD');

            if ($returnVar !== 0) {
                $this->error('Database backup failed!');
                $this->error('Output: ' . implode("\n", $output));
                Log::error('Database backup failed', [
                    'output' => $output,
                    'return_code' => $returnVar,
                ]);
                return self::FAILURE;
            }

            // Compress if requested
            if ($this->option('compress')) {
                $this->info('Compressing backup...');
                $compressedFile = $filepath . '.gz';

                if (function_exists('gzencode')) {
                    $data = file_get_contents($filepath);
                    file_put_contents($compressedFile, gzencode($data, 9));
                    unlink($filepath);
                    $filepath = $compressedFile;
                    $filename .= '.gz';
                } else {
                    $this->warn('gzencode not available, skipping compression');
                }
            }

            $fileSize = $this->formatBytes(filesize($filepath));
            $this->info("✓ Backup created successfully: {$filename} ({$fileSize})");

            // Clean old backups
            $retention = (int) $this->option('retention');
            $this->cleanOldBackups($backupDir, $retention);

            // Log success
            Log::info('Database backup completed', [
                'filename' => $filename,
                'size' => $fileSize,
                'path' => $filepath,
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Backup failed: ' . $e->getMessage());
            Log::error('Database backup exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Clean old backup files
     */
    private function cleanOldBackups(string $directory, int $retentionDays): void
    {
        $this->info("Cleaning backups older than {$retentionDays} days...");

        $files = glob($directory . '/db_backup_*.sql*');
        $cutoffTime = Carbon::now()->subDays($retentionDays)->timestamp;
        $deletedCount = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                $deletedCount++;
                $this->line("  Deleted: " . basename($file));
            }
        }

        if ($deletedCount > 0) {
            $this->info("✓ Cleaned {$deletedCount} old backup(s)");
        } else {
            $this->info("✓ No old backups to clean");
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
