<?php

namespace App\Console\Commands;

use App\Mail\DailyReportMail;
use App\Models\Salon;
use App\Services\DailyReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendDailyReportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reports:send-daily
                            {--date= : Date to generate report for (Y-m-d format, defaults to yesterday)}
                            {--salon= : Specific salon ID to send report for (optional)}
                            {--force : Force send even if already sent today}';

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

        // Determine date for report
        $dateInput = $this->option('date');
        // Use TODAY for automatic sending (sent in evening after work hours)
        $date = $dateInput ? Carbon::parse($dateInput) : Carbon::today();

        $this->info("Generating reports for: {$date->format('d.m.Y')}");

        // Get salons with daily reports enabled
        $query = Salon::whereHas('settings', function ($q) {
            $q->where('daily_report_enabled', true);
        })->with(['settings', 'owner']);

        // Filter by specific salon if provided
        if ($salonId = $this->option('salon')) {
            $query->where('id', $salonId);
        }

        $salons = $query->get();

        if ($salons->isEmpty()) {
            $this->warn('No salons found with daily reports enabled.');
            return Command::SUCCESS;
        }

        $this->info("Found {$salons->count()} salon(s) with daily reports enabled.");

        $successCount = 0;
        $failureCount = 0;

        $progressBar = $this->output->createProgressBar($salons->count());
        $progressBar->start();

        foreach ($salons as $salon) {
            try {
                // Generate report data
                $reportData = $this->reportService->generateReport($salon, $date);

                // Determine recipient email
                $recipientEmail = $salon->settings->report_email ?? $salon->owner->email;

                if (!$recipientEmail) {
                    $this->newLine();
                    $this->warn("Skipping {$salon->name}: No email address found");
                    $failureCount++;
                    $progressBar->advance();
                    continue;
                }

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
                ['Success', $successCount],
                ['Failed', $failureCount],
                ['Total', $salons->count()],
            ]
        );

        return Command::SUCCESS;
    }
}
