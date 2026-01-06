<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\Salon;
use App\Services\ImportService;
use App\Jobs\ProcessImportJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    public function __construct(
        private ImportService $importService
    ) {}

    /**
     * Upload and parse file.
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:json,csv,xlsx|max:10240', // 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $filename = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();

            // Parse file
            $data = $this->importService->parseFile($file, $extension);

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File is empty or invalid format',
                ], 422);
            }

            // Detect columns
            $columns = $this->importService->detectColumns($data);

            // Generate unique ID for this import
            $importId = Str::uuid()->toString();

            // Cache parsed data for 15 minutes
            Cache::put("import_data_{$importId}", $data, now()->addMinutes(15));
            Cache::put("import_filename_{$importId}", $filename, now()->addMinutes(15));

            // Get preview (first 10 rows)
            $preview = array_slice($data, 0, 10);

            return response()->json([
                'success' => true,
                'data' => [
                    'import_id' => $importId,
                    'filename' => $filename,
                    'total_rows' => count($data),
                    'detected_columns' => $columns,
                    'preview' => $preview,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error parsing file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get preview of imported data.
     */
    public function preview(string $importId): JsonResponse
    {
        $data = Cache::get("import_data_{$importId}");
        $filename = Cache::get("import_filename_{$importId}");

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Import data not found or expired',
            ], 404);
        }

        $preview = array_slice($data, 0, 10);
        $columns = $this->importService->detectColumns($data);

        return response()->json([
            'success' => true,
            'data' => [
                'filename' => $filename,
                'total_rows' => count($data),
                'columns' => $columns,
                'preview' => $preview,
            ],
        ]);
    }

    /**
     * Validate data and show mapping.
     */
    public function validate(Request $request, string $importId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'salon_id' => 'required|exists:salons,id',
            'staff_id' => 'required|exists:staff,id',
            'mapping' => 'required|array',
            'auto_map_services' => 'boolean',
            'create_guest_users' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = Cache::get("import_data_{$importId}");

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Import data not found or expired',
            ], 404);
        }

        try {
            $salonId = $request->salon_id;
            $autoMapServices = $request->auto_map_services ?? true;
            $createGuestUsers = $request->create_guest_users ?? true;

            $validRows = 0;
            $invalidRows = 0;
            $errors = [];

            // Validate each row
            foreach ($data as $index => $row) {
                $rowErrors = $this->importService->validateRow($row, $index + 1);

                if (empty($rowErrors)) {
                    $validRows++;
                } else {
                    $invalidRows++;
                    $errors[] = [
                        'row' => $index + 1,
                        'data' => $row,
                        'errors' => $rowErrors,
                    ];
                }
            }

            // Get service mapping stats
            $serviceMapping = null;
            if ($autoMapServices) {
                $serviceMapping = $this->importService->getServiceMappingStats($data, $salonId);
            }

            // Get user creation stats
            $userCreation = null;
            if ($createGuestUsers) {
                $userCreation = $this->importService->getUserCreationStats($data);
            }

            // Cache validation results
            Cache::put("import_validation_{$importId}", [
                'valid_rows' => $validRows,
                'invalid_rows' => $invalidRows,
                'errors' => $errors,
            ], now()->addMinutes(15));

            return response()->json([
                'success' => true,
                'data' => [
                    'valid_rows' => $validRows,
                    'invalid_rows' => $invalidRows,
                    'errors' => array_slice($errors, 0, 50), // Limit to 50 errors
                    'service_mapping' => $serviceMapping,
                    'user_creation' => $userCreation,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process import (start job).
     */
    public function process(Request $request, string $importId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'salon_id' => 'required|exists:salons,id',
            'staff_id' => 'required|exists:staff,id',
            'mapping' => 'required|array',
            'auto_map_services' => 'boolean',
            'create_guest_users' => 'boolean',
            'skip_invalid' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = Cache::get("import_data_{$importId}");
        $filename = Cache::get("import_filename_{$importId}");
        $validation = Cache::get("import_validation_{$importId}");

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Import data not found or expired',
            ], 404);
        }

        try {
            $salonId = $request->salon_id;
            $staffId = $request->staff_id;
            $skipInvalid = $request->skip_invalid ?? true;

            // Filter out invalid rows if skip_invalid is true
            if ($skipInvalid && $validation) {
                $invalidRowNumbers = array_column($validation['errors'], 'row');
                $data = array_filter($data, function($index) use ($invalidRowNumbers) {
                    return !in_array($index + 1, $invalidRowNumbers);
                }, ARRAY_FILTER_USE_KEY);
                $data = array_values($data); // Re-index array
            }

            // Create import batch record
            $importBatch = ImportBatch::create([
                'salon_id' => $salonId,
                'user_id' => auth()->id(),
                'filename' => $filename,
                'total_rows' => count($data),
                'status' => 'pending',
            ]);

            // Dispatch job
            ProcessImportJob::dispatch(
                $importBatch->id,
                $data,
                $salonId,
                $staffId,
                $request->auto_map_services ?? true,
                $request->create_guest_users ?? true
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'import_batch_id' => $importBatch->id,
                    'message' => 'Import started. You will be notified when complete.',
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error starting import: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get import status.
     */
    public function status(int $importBatchId): JsonResponse
    {
        $importBatch = ImportBatch::find($importBatchId);

        if (!$importBatch) {
            return response()->json([
                'success' => false,
                'message' => 'Import batch not found',
            ], 404);
        }

        // Check authorization
        if ($importBatch->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $progress = 0;
        if ($importBatch->total_rows > 0) {
            $progress = round((($importBatch->successful_rows + $importBatch->failed_rows) / $importBatch->total_rows) * 100, 2);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $importBatch->status,
                'progress' => $progress,
                'total_rows' => $importBatch->total_rows,
                'successful_rows' => $importBatch->successful_rows,
                'failed_rows' => $importBatch->failed_rows,
                'started_at' => $importBatch->started_at,
                'completed_at' => $importBatch->completed_at,
            ],
        ]);
    }

    /**
     * Get import history.
     */
    public function history(Request $request): JsonResponse
    {
        $query = ImportBatch::with(['salon', 'user'])
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc');

        // Filter by salon if provided
        if ($request->has('salon_id')) {
            $query->where('salon_id', $request->salon_id);
        }

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $imports = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $imports,
        ]);
    }

    /**
     * Download errors as CSV.
     */
    public function downloadErrors(int $importBatchId): mixed
    {
        $importBatch = ImportBatch::find($importBatchId);

        if (!$importBatch) {
            return response()->json([
                'success' => false,
                'message' => 'Import batch not found',
            ], 404);
        }

        // Check authorization
        if ($importBatch->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if (empty($importBatch->errors)) {
            return response()->json([
                'success' => false,
                'message' => 'No errors found',
            ], 404);
        }

        // Generate CSV
        $csv = "Row,Error,Data\n";
        foreach ($importBatch->errors as $error) {
            $row = $error['row'] ?? 'N/A';
            $errorMsg = $error['error'] ?? 'Unknown error';
            $data = json_encode($error['data'] ?? []);
            $csv .= "\"{$row}\",\"{$errorMsg}\",\"{$data}\"\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"import_errors_{$importBatchId}.csv\"",
        ]);
    }

    /**
     * Download template file.
     */
    public function downloadTemplate(string $format): mixed
    {
        $templates = [
            'json' => storage_path('app/templates/appointments-template.json'),
            'csv' => storage_path('app/templates/appointments-template.csv'),
        ];

        if (!isset($templates[$format])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid format',
            ], 400);
        }

        $filePath = $templates[$format];

        if (!file_exists($filePath)) {
            // Create template on the fly
            if ($format === 'json') {
                $content = json_encode([
                    [
                        'name' => 'Ime Prezime',
                        'email' => 'email@example.com',
                        'phone' => '062123456',
                        'date' => '2026-01-17',
                        'time' => '12:00',
                        'services' => 'Šišanje, Brijanje',
                        'duration' => 60,
                        'notes' => 'Napomena (opciono)',
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } else {
                // CSV with semicolon delimiter (supports commas in services field)
                $content = "name;email;phone;date;time;services;duration;notes\n";
                $content .= "Ime Prezime;email@example.com;062123456;2026-01-17;12:00;Šišanje, Brijanje;60;Napomena\n";
                $content .= "Ana Anić;;061234567;2026-01-17;13:00;Šišanje;30;Bez email-a\n";
                $content .= "Petar Petrović;petar@example.com;;2026-01-17;14:00;Buzz cut;20;Bez telefona\n";
            }

            return response($content, 200, [
                'Content-Type' => $format === 'json' ? 'application/json' : 'text/csv',
                'Content-Disposition' => "attachment; filename=\"appointments-template.{$format}\"",
            ]);
        }

        return response()->download($filePath);
    }
}
