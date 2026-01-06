<?php

namespace App\Services;

use App\Models\User;
use App\Models\Service;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportService
{
    /**
     * Parse file based on format.
     */
    public function parseFile($file, string $format): array
    {
        switch ($format) {
            case 'json':
                return $this->parseJson($file);
            case 'csv':
                return $this->parseCsv($file);
            case 'xlsx':
                return $this->parseExcel($file);
            default:
                throw new \Exception('Unsupported file format');
        }
    }

    /**
     * Parse JSON file.
     */
    private function parseJson($file): array
    {
        $content = file_get_contents($file->getRealPath());
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON format: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Parse CSV file with semicolon or comma delimiter.
     */
    private function parseCsv($file): array
    {
        $data = [];
        $handle = fopen($file->getRealPath(), 'r');

        // Read first line to detect delimiter
        $firstLine = fgets($handle);
        rewind($handle);

        // Detect delimiter (semicolon or comma)
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

        // Read header
        $header = fgetcsv($handle, 0, $delimiter);

        // Read rows
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($row) === count($header)) {
                $data[] = array_combine($header, $row);
            }
        }

        fclose($handle);

        return $data;
    }

    /**
     * Parse Excel file (.xlsx, .xls).
     */
    private function parseExcel($file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();

            // Get highest row and column
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();

            // Read header row
            $header = [];
            foreach ($worksheet->getRowIterator(1, 1) as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $value = $cell->getValue();
                    if (!empty($value)) {
                        $header[] = strtolower(trim($value)); // Normalize header names
                    }
                }
            }

            if (empty($header)) {
                return [];
            }

            // Read data rows
            $data = [];
            for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
                $rowData = [];
                $isEmpty = true;

                for ($colIndex = 0; $colIndex < count($header); $colIndex++) {
                    // Get cell using column letter and row number
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                    $cell = $worksheet->getCell($columnLetter . $rowIndex);
                    $value = $cell->getValue();
                    $columnName = $header[$colIndex];

                    // Handle date/time cells based on column name
                    if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                        try {
                            $dateTimeValue = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);

                            // Check if this is a time column (not date)
                            if (in_array($columnName, ['time', 'vrijeme', 'vreme'])) {
                                // Format as time only (HH:MM)
                                $value = $dateTimeValue->format('H:i');
                            } else {
                                // Format as date (YYYY-MM-DD)
                                $value = $dateTimeValue->format('Y-m-d');
                            }
                        } catch (\Exception $e) {
                            // If conversion fails, use formatted value
                            $value = $cell->getFormattedValue();
                        }
                    } else {
                        // Use formatted value for other cells
                        $value = $cell->getFormattedValue();
                    }

                    if (!empty($value)) {
                        $isEmpty = false;
                    }

                    $rowData[$columnName] = $value;
                }

                // Skip empty rows
                if (!$isEmpty) {
                    $data[] = $rowData;
                }
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Excel parsing error', ['error' => $e->getMessage()]);
            throw new \Exception('Error parsing Excel file: ' . $e->getMessage());
        }
    }

    /**
     * Detect columns from data.
     */
    public function detectColumns(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        return array_keys($data[0]);
    }

    /**
     * Validate single row.
     */
    public function validateRow(array $row, int $rowNumber): array
    {
        $errors = [];

        // Required fields
        if (empty($row['name']) || strlen(trim($row['name'])) < 2) {
            $errors[] = 'Name is required (min 2 characters)';
        }

        // Email is optional, but if provided must be valid
        if (!empty($row['email']) && trim($row['email']) !== '' && !filter_var(trim($row['email']), FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        // Phone is optional, but if provided must be valid
        if (!empty($row['phone']) && strlen(preg_replace('/\D/', '', $row['phone'])) < 6) {
            $errors[] = 'Invalid phone number (min 6 digits)';
        }

        // Date validation
        if (empty($row['date']) || trim($row['date']) === '') {
            $errors[] = 'Date is required';
        } elseif (!$this->isValidDate(trim($row['date']))) {
            Log::warning('Date validation failed', [
                'row' => $rowNumber,
                'date_value' => $row['date'],
                'date_type' => gettype($row['date']),
                'date_length' => strlen($row['date']),
            ]);
            $errors[] = 'Valid date is required (YYYY-MM-DD format)';
        }

        // Time validation
        if (empty($row['time']) || trim($row['time']) === '') {
            $errors[] = 'Time is required';
        } elseif (!$this->isValidTime(trim($row['time']))) {
            Log::warning('Time validation failed', [
                'row' => $rowNumber,
                'time_value' => $row['time'],
                'time_type' => gettype($row['time']),
            ]);
            $errors[] = 'Valid time is required (HH:MM format)';
        }

        // Duration validation
        $duration = trim($row['duration'] ?? '');
        if ($duration === '') {
            $errors[] = 'Duration is required';
        } elseif (!is_numeric($duration) || $duration < 5 || $duration > 480) {
            $errors[] = 'Duration must be between 5 and 480 minutes';
        }

        return $errors;
    }

    /**
     * Normalize phone number.
     */
    public function normalizePhone(string $phone): string
    {
        // Remove all non-digit characters
        $phone = preg_replace('/\D/', '', $phone);

        // Remove +387 prefix
        if (str_starts_with($phone, '387')) {
            $phone = substr($phone, 3);
        }

        // Add 0 prefix if missing
        if (!str_starts_with($phone, '0') && strlen($phone) === 8) {
            $phone = '0' . $phone;
        }

        return $phone;
    }

    /**
     * Calculate end time.
     */
    public function calculateEndTime(string $time, int $duration): string
    {
        $startTime = Carbon::createFromFormat('H:i', $time);
        $endTime = $startTime->addMinutes($duration);

        return $endTime->format('H:i');
    }

    /**
     * Map service by name.
     */
    public function mapService(string $serviceName, int $salonId): ?int
    {
        // Get all services for the salon
        $services = Service::where('salon_id', $salonId)
            ->where('is_active', true)
            ->get();

        // Normalize service name
        $normalizedName = $this->normalizeServiceName($serviceName);

        // Try exact match first
        foreach ($services as $service) {
            if ($this->normalizeServiceName($service->name) === $normalizedName) {
                return $service->id;
            }
        }

        // Try fuzzy match (90% similarity)
        foreach ($services as $service) {
            $similarity = 0;
            similar_text(
                $this->normalizeServiceName($service->name),
                $normalizedName,
                $similarity
            );

            if ($similarity >= 90) {
                return $service->id;
            }
        }

        // No match found
        return null;
    }

    /**
     * Normalize service name for comparison.
     */
    private function normalizeServiceName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * Find or create guest user.
     */
    public function findOrCreateGuestUser(array $data): User
    {
        // Generate dummy email if missing
        if (empty($data['email'])) {
            $data['email'] = 'guest_' . Str::random(10) . '@import.local';
        }

        // Generate dummy phone if missing
        if (empty($data['phone'])) {
            $data['phone'] = '060' . rand(1000000, 9999999);
        }

        // Try to find existing user by email
        $user = User::where('email', $data['email'])->first();

        if ($user) {
            // Update user info if name is longer (more complete)
            if (strlen($data['name']) > strlen($user->name)) {
                $user->update(['name' => $data['name']]);
            }

            // Update phone if different and not dummy
            if (!empty($data['phone']) && $user->phone !== $data['phone'] && !str_starts_with($data['email'], 'guest_')) {
                $user->update(['phone' => $data['phone']]);
            }

            return $user;
        }

        // Create new guest user
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $this->normalizePhone($data['phone']),
            'password' => bcrypt(Str::random(32)), // Random password for guest users
            'email_verified_at' => null,
            'role' => 'klijent',
            'is_guest' => true,
            'created_via' => 'import',
        ]);
    }

    /**
     * Import single row.
     */
    public function importRow(
        array $row,
        int $salonId,
        int $staffId,
        int $importBatchId,
        bool $autoMapServices = true,
        bool $createGuestUsers = true
    ): array {
        try {
            // Find or create user
            $clientId = null;
            if ($createGuestUsers) {
                $user = $this->findOrCreateGuestUser([
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                ]);
                $clientId = $user->id;
            }

            // Map service and handle multiple services
            $serviceId = null;
            $totalPrice = 0;
            $additionalNotes = '';
            if ($autoMapServices && !empty($row['services'])) {
                // Handle multiple services (split by comma)
                $services = array_map('trim', explode(',', $row['services']));

                // Map first service
                $serviceId = $this->mapService($services[0], $salonId);

                // Get service price
                if ($serviceId) {
                    $service = Service::find($serviceId);
                    if ($service) {
                        // Use service price, or 10 if price is 0 or null
                        $totalPrice = $service->price > 0 ? $service->price : 10;
                    } else {
                        Log::warning('Service not found after mapping', ['service_id' => $serviceId]);
                        $totalPrice = 10;
                    }
                } else {
                    Log::warning('Service mapping failed', [
                        'service_name' => $services[0],
                        'salon_id' => $salonId
                    ]);
                    $totalPrice = 10;
                }

                // Add other services to notes
                if (count($services) > 1) {
                    $otherServices = array_slice($services, 1);
                    $additionalNotes = 'Dodatne usluge: ' . implode(', ', $otherServices);
                }
            }

            // Normalize date to YYYY-MM-DD format
            $normalizedDate = $this->normalizeDate($row['date']);

            // Normalize time to HH:MM format (strip seconds if present)
            $normalizedTime = $this->normalizeTime($row['time']);

            // Calculate end time
            $endTime = $this->calculateEndTime($normalizedTime, $row['duration']);

            // Combine notes
            $notes = trim(($row['notes'] ?? '') . ($additionalNotes ? "\n" . $additionalNotes : ''));

            // Generate dummy email/phone if missing
            $clientEmail = !empty($row['email']) ? $row['email'] : 'guest_' . Str::random(10) . '@import.local';
            $clientPhone = !empty($row['phone']) ? $this->normalizePhone($row['phone']) : '060' . rand(1000000, 9999999);

            // Debug log
            Log::info('Creating appointment', [
                'service_id' => $serviceId,
                'total_price' => $totalPrice,
                'service_name' => $row['services'] ?? 'N/A'
            ]);

            // Create appointment
            $appointment = Appointment::create([
                'salon_id' => $salonId,
                'staff_id' => $staffId,
                'service_id' => $serviceId,
                'client_id' => $clientId,
                'client_name' => $row['name'],
                'client_email' => $clientEmail,
                'client_phone' => $clientPhone,
                'date' => $normalizedDate,
                'time' => $normalizedTime,
                'end_time' => $endTime,
                'status' => 'confirmed',
                'notes' => $notes ?: null,
                'total_price' => $totalPrice,
                'source' => 'import',
                'import_batch_id' => $importBatchId,
            ]);

            return [
                'success' => true,
                'appointment_id' => $appointment->id,
            ];
        } catch (\Exception $e) {
            Log::error('Import row error', [
                'row' => $row,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate date format (supports multiple formats).
     */
    private function isValidDate(string $date): bool
    {
        // Handle empty dates
        if (empty($date)) {
            return false;
        }

        // Trim whitespace
        $date = trim($date);

        // Handle Excel numeric date format (days since 1900-01-01)
        if (is_numeric($date)) {
            try {
                $excelEpoch = Carbon::create(1900, 1, 1);
                $parsed = $excelEpoch->addDays((int)$date - 2); // Excel has a leap year bug
                return $parsed->year >= 1900 && $parsed->year <= 2100;
            } catch (\Exception $e) {
                // Continue to other formats
            }
        }

        $formats = [
            'Y-m-d',      // 2026-01-16
            'd.m.Y',      // 16.1.2026
            'd.n.Y',      // 16.1.2026 (single digit month)
            'j.n.Y',      // 6.1.2026 (single digit day and month)
            'd/m/Y',      // 16/01/2026
            'd/n/Y',      // 16/1/2026 (single digit month)
            'j/n/Y',      // 6/1/2026 (single digit day and month)
            'd-m-Y',      // 16-01-2026
            'd-n-Y',      // 16-1-2026 (single digit month)
            'j-n-Y',      // 6-1-2026 (single digit day and month)
            'd.m.y',      // 16.1.26 (2-digit year)
            'j.n.y',      // 6.1.26 (2-digit year)
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $date);
                if ($parsed) {
                    // Verify the parsed date makes sense
                    if ($parsed->year >= 1900 && $parsed->year <= 2100) {
                        return true;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return false;
    }

    /**
     * Normalize date to YYYY-MM-DD format.
     */
    private function normalizeDate(string $date): string
    {
        // Trim whitespace
        $date = trim($date);

        // Handle Excel numeric date format (days since 1900-01-01)
        if (is_numeric($date)) {
            try {
                $excelEpoch = Carbon::create(1900, 1, 1);
                $parsed = $excelEpoch->addDays((int)$date - 2); // Excel has a leap year bug
                return $parsed->format('Y-m-d');
            } catch (\Exception $e) {
                // Continue to other formats
            }
        }

        $formats = [
            'Y-m-d',      // 2026-01-16
            'd.m.Y',      // 16.1.2026
            'd.n.Y',      // 16.1.2026 (single digit month)
            'j.n.Y',      // 6.1.2026 (single digit day and month)
            'd/m/Y',      // 16/01/2026
            'd/n/Y',      // 16/1/2026 (single digit month)
            'j/n/Y',      // 6/1/2026 (single digit day and month)
            'd-m-Y',      // 16-01-2026
            'd-n-Y',      // 16-1-2026 (single digit month)
            'j-n-Y',      // 6-1-2026 (single digit day and month)
            'd.m.y',      // 16.1.26 (2-digit year)
            'j.n.y',      // 6.1.26 (2-digit year)
            'd.n.Y',      // 16.1.2026 (single digit month)
            'd/m/Y',      // 16/01/2026
            'd/n/Y',      // 16/1/2026 (single digit month)
            'd-m-Y',      // 16-01-2026
            'd-n-Y',      // 16-1-2026 (single digit month)
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $date);
                if ($parsed) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // If all formats fail, return original (will fail validation)
        return $date;
    }

    /**
     * Validate time format (supports HH:MM and HH:MM:SS).
     */
    private function isValidTime(string $time): bool
    {
        // Trim whitespace
        $time = trim($time);

        // Match HH:MM or HH:MM:SS format
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $time) === 1;
    }

    /**
     * Normalize time to HH:MM format (strip seconds if present).
     */
    private function normalizeTime(string $time): string
    {
        // Trim whitespace
        $time = trim($time);

        // If time has seconds (HH:MM:SS), strip them
        if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $time)) {
            return substr($time, 0, 5); // Take only HH:MM part
        }

        return $time;
    }

    /**
     * Get service mapping statistics.
     */
    public function getServiceMappingStats(array $data, int $salonId): array
    {
        $matched = 0;
        $unmatched = 0;
        $mappings = [];

        foreach ($data as $row) {
            if (empty($row['services'])) {
                continue;
            }

            $services = array_map('trim', explode(',', $row['services']));

            foreach ($services as $serviceName) {
                $serviceId = $this->mapService($serviceName, $salonId);

                if ($serviceId) {
                    $matched++;
                    $service = Service::find($serviceId);
                    $mappings[] = [
                        'import_name' => $serviceName,
                        'service_id' => $serviceId,
                        'service_name' => $service->name,
                        'match_type' => 'exact', // TODO: Detect fuzzy vs exact
                    ];
                } else {
                    $unmatched++;
                    $mappings[] = [
                        'import_name' => $serviceName,
                        'service_id' => null,
                        'service_name' => null,
                        'match_type' => 'none',
                    ];
                }
            }
        }

        return [
            'matched' => $matched,
            'unmatched' => $unmatched,
            'mappings' => array_unique($mappings, SORT_REGULAR),
        ];
    }

    /**
     * Get user creation statistics.
     */
    public function getUserCreationStats(array $data): array
    {
        $existingUsers = 0;
        $newGuestUsers = 0;

        foreach ($data as $row) {
            if (empty($row['email'])) {
                continue;
            }

            $user = User::where('email', $row['email'])->first();

            if ($user) {
                $existingUsers++;
            } else {
                $newGuestUsers++;
            }
        }

        return [
            'existing_users' => $existingUsers,
            'new_guest_users' => $newGuestUsers,
        ];
    }
}
