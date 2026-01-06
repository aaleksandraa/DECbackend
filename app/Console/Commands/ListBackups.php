<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

class ListBackups extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backup:list
                            {--type=all : Type of backups to list (all, database, files)}';

    /**
     * The console command description.
     */
    protected $description = 'List all available backups';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->option('type');
        $backupDir = storage_path('app/backups');

        if (!is_dir($backupDir)) {
            $this->warn('No backups directory found.');
            return self::SUCCESS;
        }

        $this->info('📦 Available Backups');
        $this->newLine();

        // List database backups
        if ($type === 'all' || $type === 'database') {
            $this->listBackupType('Database', $backupDir . '/database', 'db_backup_*.sql*');
        }

        // List files backups
        if ($type === 'all' || $type === 'files') {
            $this->listBackupType('Files', $backupDir . '/files', 'files_backup_*.zip');
        }

        return self::SUCCESS;
    }

    /**
     * List backups of a specific type
     */
    private function listBackupType(string $name, string $directory, string $pattern): void
    {
        if (!is_dir($directory)) {
            $this->warn("{$name} backups directory not found.");
            $this->newLine();
            return;
        }

        $files = glob($directory . '/' . $pattern);

        if (empty($files)) {
            $this->warn("No {$name} backups found.");
            $this->newLine();
            return;
        }

        // Sort by modification time (newest first)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $this->info("🗄️  {$name} Backups:");
        $this->newLine();

        $headers = ['Filename', 'Size', 'Created', 'Age'];
        $rows = [];

        foreach ($files as $file) {
            $filename = basename($file);
            $size = $this->formatBytes(filesize($file));
            $created = date('Y-m-d H:i:s', filemtime($file));
            $age = Carbon::createFromTimestamp(filemtime($file))->diffForHumans();

            $rows[] = [$filename, $size, $created, $age];
        }

        $this->table($headers, $rows);
        $this->info("Total: " . count($files) . " backup(s)");
        $this->newLine();
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
