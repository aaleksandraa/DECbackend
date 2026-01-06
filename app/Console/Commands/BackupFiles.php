<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use ZipArchive;

class BackupFiles extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backup:files
                            {--retention=30 : Number of days to keep backups}';

    /**
     * The console command description.
     */
    protected $description = 'Create a backup of uploaded files (images, avatars)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting files backup...');

        try {
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');

            // Create backup directory if it doesn't exist
            $backupDir = storage_path('app' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'files');
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $filename = "files_backup_{$timestamp}.zip";
            $filepath = $backupDir . DIRECTORY_SEPARATOR . $filename;

            // Directories to backup
            $sourceDirs = [
                storage_path('app' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'salons'),
                storage_path('app' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'avatars'),
                storage_path('app' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'services'),
            ];

            // Create ZIP archive
            $zip = new ZipArchive();
            $zipResult = $zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($zipResult !== true) {
                $this->error("Failed to create ZIP archive. Error code: {$zipResult}");
                $this->error("Filepath: {$filepath}");
                return self::FAILURE;
            }

            $fileCount = 0;
            foreach ($sourceDirs as $sourceDir) {
                if (!is_dir($sourceDir)) {
                    $this->warn("Directory not found, skipping: {$sourceDir}");
                    continue;
                }

                $this->info("Adding files from: " . basename($sourceDir));
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($sourceDir),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $publicPath = storage_path('app' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
                        $relativePath = str_replace($publicPath, '', $filePath);

                        $zip->addFile($filePath, $relativePath);
                        $fileCount++;
                    }
                }
            }

            // Check if any files were added
            if ($fileCount === 0) {
                $zip->close();
                @unlink($filepath);
                $this->warn('No files found to backup');
                return self::SUCCESS;
            }

            $zip->close();

            // Check if file was created
            if (!file_exists($filepath)) {
                $this->error('ZIP file was not created');
                return self::FAILURE;
            }

            $fileSize = $this->formatBytes(filesize($filepath));
            $this->info("✓ Files backup created successfully: {$filename}");
            $this->info("  Files included: {$fileCount}");
            $this->info("  Archive size: {$fileSize}");

            // Clean old backups
            $retention = (int) $this->option('retention');
            $this->cleanOldBackups($backupDir, $retention);

            // Log success
            Log::info('Files backup completed', [
                'filename' => $filename,
                'file_count' => $fileCount,
                'size' => $fileSize,
                'path' => $filepath,
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Backup failed: ' . $e->getMessage());
            Log::error('Files backup exception', [
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

        $files = glob($directory . '/files_backup_*.zip');
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
