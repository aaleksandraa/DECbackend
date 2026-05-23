<?php

namespace App\Console\Commands;

use App\Mail\DailyReportMail;
use App\Models\Salon;
use App\Services\DailyReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendDailyReportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reports:send-daily
                            {--date= : Date to generate report for (Y-m-d format, defaults to today)}
                            {--salon= : Specific salon ID to send report for (optional)}
                            {--force : Force send even if already sent for same salon/date}
                            {--dry-run : Preview eligible salons without sending emails}';

    /**
     * The console command description.
     */
    protected $description = 'Send daily reports to salon owners';

    protected DailyReportService $reportService;

    /**
     * Create a new command instance.
     */
    public function __construct(DailyReportService $reportService)
    {
        parent::__construct();
        $this->reportService = $reportService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting daily report generation...');

        $timezone = 'Europe/Sarajevo';
        $now = Carbon::now($timezone);
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $salonId = $this->option('salon');

        // Determine date for report
        $dateInput = $this->option('date');
        // Use TODAY for automatic sending.
        $date = $dateInput ? Carbon::parse($dateInput, $timezone) : Carbon::today($timezone);

        $this->info("Generating reports for: {$date->format('d.m.Y')}");
        $this->info("Current scheduler time: {$now->format('H:i')} ({$timezone})");

        // Get salons with daily reports enabled
        $query = Salon::whereHas('settings', function ($q) {
            $q->where('daily_report_enabled', true);
        })->with(['settings', 'owner']);

        // Filter by specific salon if provided
        if ($salonId) {
            $query->where('id', $salonId);
        }

        $salons = $query->get();

        if ($salons->isEmpty()) {
            $this->warn('No salons found with daily reports enabled.');
            return Command::SUCCESS;
        }

        $isTargetedRun = !empty($salonId);
        $isScheduledMode = !$isTargetedRun && empty($dateInput);
        if ($isScheduledMode && !$force) {
            $currentMinute = $now->format('H:i');
            $salons = $salons
                ->filter(fn (Salon $salon) => $this->isDueForCurrentMinute($salon, $currentMinute))
                ->values();
        }

        if ($salons->isEmpty()) {
            $this->info('No salons are due for daily report at this minute.');
            return Command::SUCCESS;
        }

        $this->info("Found {$salons->count()} eligible salon(s).");

        $successCount = 0;
        $failureCount = 0;
        $skippedNoEmail = 0;
        $skippedDuplicate = 0;
        $previewCount = 0;

        $progressBar = $this->output->createProgressBar($salons->count());
        $progressBar->start();

        foreach ($salons as $salon) {
            $lockKey = null;

            try {
                // Determine recipient email
                $recipientEmail = $salon->settings->daily_report_email ?: $salon->owner->email;

                if (!$recipientEmail) {
                    $this->newLine();
                    $this->warn("Skipping {$salon->name}: No email address found");
                    $skippedNoEmail++;
                    $progressBar->advance();
                    continue;
                }

                if ($dryRun) {
                    $previewCount++;
                    $progressBar->advance();
                    continue;
                }

                // Prevent duplicate sends per salon/date unless forced.
                if (!$force) {
                    $lockKey = $this->buildSendLockKey((int) $salon->id, $date);
                    if (!Cache::add($lockKey, $now->timestamp, $this->sendLockTtlSeconds($now))) {
                        $skippedDuplicate++;
                        $progressBar->advance();
                        continue;
                    }
                }

                // Generate report data
                $reportData = $this->reportService->generateReport($salon, $date);

                // Send email
                Mail::to($recipientEmail)->send(new DailyReportMail($salon, $reportData, $date));

                $successCount++;

                Log::info('Daily report sent', [
                    'salon_id' => $salon->id,
                    'salon_name' => $salon->name,
                    'recipient' => $recipientEmail,
                    'date' => $date->format('Y-m-d'),
                ]);

            } catch (\Exception $e) {
                if ($lockKey) {
                    Cache::forget($lockKey);
                }

                $failureCount++;

                $this->newLine();
                $this->error("Failed to send report for {$salon->name}: {$e->getMessage()}");

                Log::error('Daily report failed', [
                    'salon_id' => $salon->id,
                    'salon_name' => $salon->name,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info("Daily report generation completed!");
        $this->table(
            ['Status', 'Count'],
            [
                ['Preview (dry-run)', $previewCount],
                ['Success', $successCount],
                ['Failed', $failureCount],
                ['Skipped (no email)', $skippedNoEmail],
                ['Skipped (already sent)', $skippedDuplicate],
                ['Total', $salons->count()],
            ]
        );

        return Command::SUCCESS;
    }

    private function isDueForCurrentMinute(Salon $salon, string $currentMinute): bool
    {
        $configuredTime = (string) ($salon->settings?->daily_report_time ?? '');
        if ($configuredTime === '') {
            return false;
        }

        // Compare HH:MM lexicographically (safe for zero-padded 24h time).
        return substr($configuredTime, 0, 5) <= $currentMinute;
    }

    private function buildSendLockKey(int $salonId, Carbon $date): string
    {
        return "daily_report:{$date->format('Y-m-d')}:{$salonId}";
    }

    private function sendLockTtlSeconds(Carbon $now): int
    {
        return max(3600, $now->copy()->endOfDay()->addMinutes(5)->diffInSeconds($now));
    }
}
