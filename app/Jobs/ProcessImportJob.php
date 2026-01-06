<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\ImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $importBatchId,
        public array $data,
        public int $salonId,
        public int $staffId,
        public bool $autoMapServices = true,
        public bool $createGuestUsers = true
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ImportService $importService): void
    {
        $importBatch = ImportBatch::find($this->importBatchId);

        if (!$importBatch) {
            Log::error('Import batch not found', ['id' => $this->importBatchId]);
            return;
        }

        try {
            // Update status to processing
            $importBatch->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            $successfulRows = 0;
            $failedRows = 0;
            $errors = [];

            // Process rows in batches of 100
            $chunks = array_chunk($this->data, 100);

            foreach ($chunks as $chunkIndex => $chunk) {
                foreach ($chunk as $index => $row) {
                    $rowNumber = ($chunkIndex * 100) + $index + 1;

                    $result = $importService->importRow(
                        $row,
                        $this->salonId,
                        $this->staffId,
                        $this->importBatchId,
                        $this->autoMapServices,
                        $this->createGuestUsers
                    );

                    if ($result['success']) {
                        $successfulRows++;
                    } else {
                        $failedRows++;
                        $errors[] = [
                            'row' => $rowNumber,
                            'data' => $row,
                            'error' => $result['error'],
                        ];
                    }

                    // Update progress every 10 rows
                    if ($rowNumber % 10 === 0) {
                        $importBatch->update([
                            'successful_rows' => $successfulRows,
                            'failed_rows' => $failedRows,
                        ]);
                    }
                }
            }

            // Update final status
            $importBatch->update([
                'status' => 'completed',
                'successful_rows' => $successfulRows,
                'failed_rows' => $failedRows,
                'errors' => $errors,
                'completed_at' => now(),
            ]);

            // Update prices from services table for appointments with total_price = 0
            DB::statement(
                'UPDATE appointments SET total_price = services.price
                FROM services
                WHERE appointments.service_id = services.id
                AND appointments.import_batch_id = ?
                AND appointments.total_price = 0',
                [$this->importBatchId]
            );

            // Update status to 'completed' for past appointments
            DB::statement(
                'UPDATE appointments SET status = \'completed\'
                WHERE import_batch_id = ?
                AND date < CURRENT_DATE
                AND status = \'confirmed\'',
                [$this->importBatchId]
            );

            Log::info('Import completed', [
                'import_batch_id' => $this->importBatchId,
                'successful' => $successfulRows,
                'failed' => $failedRows,
            ]);

            // TODO: Send notification to admin

        } catch (\Exception $e) {
            Log::error('Import job failed', [
                'import_batch_id' => $this->importBatchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $importBatch->update([
                'status' => 'failed',
                'errors' => [
                    [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ],
                ],
                'completed_at' => now(),
            ]);
        }
    }
}
