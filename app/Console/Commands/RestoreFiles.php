<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class RestoreFiles extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'restore:files
                            {backup : Path to backup file (relative to storage/app/backups/files/)}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Restore files from a backup archive';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $backupFile = $this->argument('backup');

        // If relative path provided, prepend backup directory
        if (!str_starts_with($backupFile, '/') && !str_starts_with($backupFile, storage_path())) {
            $backupFile = storage_path('app/backups/files/' . $backupFile);
        }

        // Check if backup file exists
        if (!file_exists($backupFile)) {
            $this->error("Backup file not found: {$backupFile}");
            return self::FAILURE;
        }

        $this->warn('⚠️  FILES RESTORE WARNING ⚠️');
        $this->warn('This will OVERWRITE existing files!');
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

        $this->info('Starting files restore...');

        try {
            $zip = new ZipArchive();
            if ($zip->open($backupFile) !== true) {
                $this->error('Failed to open backup archive');
                return self::FAILURE;
            }

            $targetDir = storage_path('app/public');
            $fileCount = $zip->numFiles;

            $this->info("Extracting {$fileCount} files...");

            // Extract with progress
            $progressBar = $this->output->createProgressBar($fileCount);
            $progressBar->start();

            for ($i = 0; $i < $fileCount; $i++) {
                $filename = $zip->getNameIndex($i);
                $zip->extractTo($targetDir, $filename);
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();

            $zip->close();

            $this->info('✓ Files restored successfully!');
            $this->info("  Extracted {$fileCount} files to: {$targetDir}");
            $this->newLine();

            // Fix permissions
            $this->info('Setting file permissions...');
            $this->setPermissions($targetDir);
            $this->info('✓ Permissions set');

            Log::info('Files restored successfully', [
                'backup_file' => $backupFile,
                'file_count' => $fileCount,
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Restore failed: ' . $e->getMessage());
            Log::error('Files restore exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Set proper permissions for restored files
     */
    private function setPermissions(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                chmod($item->getPathname(), 0755);
            } else {
                chmod($item->getPathname(), 0644);
            }
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
