<?php

namespace App\Jobs;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupOldNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of days to keep notifications.
     */
    public int $daysToKeep;

    /**
     * Create a new job instance.
     */
    public function __construct(int $daysToKeep = 90)
    {
        $this->daysToKeep = $daysToKeep;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $cutoffDate = now()->subDays($this->daysToKeep);

            $deletedCount = Notification::where('created_at', '<', $cutoffDate)
                ->where('is_read', true)
                ->delete();

            Log::info('Old notifications cleaned up', [
                'deleted_count' => $deletedCount,
                'cutoff_date' => $cutoffDate->toDateTimeString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cleanup old notifications', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
