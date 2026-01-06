<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\DailyReportMail;
use App\Models\Salon;
use App\Models\SalonSettings;
use App\Services\DailyReportService;
use App\Services\MonthlyReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SalonSettingsController extends Controller
{
    protected DailyReportService $reportService;
    protected MonthlyReportService $monthlyReportService;

    public function __construct(DailyReportService $reportService, MonthlyReportService $monthlyReportService)
    {
        $this->reportService = $reportService;
        $this->monthlyReportService = $monthlyReportService;
    }

    /**
     * Get salon settings.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if (!$user->isSalonOwner()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $salon = $user->ownedSalon;

        if (!$salon) {
            return response()->json([
                'message' => 'Salon not found. Please complete your salon profile first.',
                'error' => 'NO_SALON'
            ], 404);
        }

        $settings = $salon->settings;

        // Create default settings if they don't exist
        if (!$settings) {
            // Use raw SQL for PostgreSQL boolean compatibility
            DB::statement("
                INSERT INTO salon_settings
                (salon_id, daily_report_enabled, daily_report_time, daily_report_include_staff,
                 daily_report_include_services, daily_report_include_capacity, daily_report_include_cancellations,
                 created_at, updated_at)
                VALUES (?, false, '20:00:00', true, true, true, true, NOW(), NOW())
            ", [$salon->id]);

            // Reload settings
            $settings = SalonSettings::where('salon_id', $salon->id)->first();
        }

        return response()->json([
            'settings' => $settings,
            'salon' => [
                'id' => $salon->id,
                'name' => $salon->name,
                'owner_email' => $salon->owner->email,
            ],
        ]);
    }

    /**
     * Update salon settings.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        if (!$user->isSalonOwner()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $salon = $user->ownedSalon;

        if (!$salon) {
            return response()->json([
                'message' => 'Salon not found. Please complete your salon profile first.',
                'error' => 'NO_SALON'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'daily_report_enabled' => 'sometimes|boolean',
            'daily_report_time' => ['sometimes', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'daily_report_email' => 'nullable|email',
            'daily_report_include_staff' => 'sometimes|boolean',
            'daily_report_include_services' => 'sometimes|boolean',
            'daily_report_include_capacity' => 'sometimes|boolean',
            'daily_report_include_cancellations' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $settings = $salon->settings;

        // Create settings if they don't exist
        if (!$settings) {
            DB::statement("
                INSERT INTO salon_settings
                (salon_id, daily_report_enabled, daily_report_time, daily_report_include_staff,
                 daily_report_include_services, daily_report_include_capacity, daily_report_include_cancellations,
                 created_at, updated_at)
                VALUES (?, false, '20:00:00', true, true, true, true, NOW(), NOW())
            ", [$salon->id]);
            $settings = SalonSettings::where('salon_id', $salon->id)->first();
        }

        // Build update data
        $updateData = [];
        $booleanFields = [
            'daily_report_enabled',
            'daily_report_include_staff',
            'daily_report_include_services',
            'daily_report_include_capacity',
            'daily_report_include_cancellations',
        ];
        $otherFields = ['daily_report_time', 'daily_report_email'];

        // Build SET clause for raw SQL
        $setClauses = [];
        $params = [];

        foreach ($booleanFields as $field) {
            if ($request->has($field)) {
                $value = $request->boolean($field) ? 'true' : 'false';
                $setClauses[] = "{$field} = {$value}";
            }
        }

        foreach ($otherFields as $field) {
            if ($request->has($field)) {
                $setClauses[] = "{$field} = ?";
                $params[] = $request->input($field);
            }
        }

        if (!empty($setClauses)) {
            $setClauses[] = "updated_at = NOW()";
            $setClause = implode(', ', $setClauses);
            $params[] = $settings->id;

            DB::statement("UPDATE salon_settings SET {$setClause} WHERE id = ?", $params);

            // Reload settings
            $settings = SalonSettings::find($settings->id);
        }

        return response()->json([
            'message' => 'Podešavanja uspješno ažurirana',
            'settings' => $settings,
        ]);
    }

    /**
     * Send test report immediately.
     */
    public function sendTestReport(Request $request)
    {
        $user = $request->user();

        if (!$user->isSalonOwner()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $salon = $user->ownedSalon;

        if (!$salon) {
            return response()->json([
                'message' => 'Salon not found. Please complete your salon profile first.',
                'error' => 'NO_SALON'
            ], 404);
        }

        $settings = $salon->settings;

        // Use TODAY's data for test report (not yesterday!)
        $date = Carbon::today();

        try {
            // Generate report data
            $reportData = $this->reportService->generateReport($salon, $date);

            // Determine email address
            $email = $settings?->daily_report_email ?? $salon->owner->email;

            // Send email immediately (not queued for test)
            Mail::to($email)->send(new DailyReportMail($salon, $reportData, $date));

            return response()->json([
                'message' => "Testni izvještaj uspješno poslan na {$email}",
                'email' => $email,
                'date' => $date->format('d.m.Y'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Greška pri slanju testnog izvještaja',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview report data without sending email.
     */
    public function previewReport(Request $request)
    {
        $user = $request->user();

        if (!$user->isSalonOwner()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $salon = $user->ownedSalon;

        if (!$salon) {
            return response()->json([
                'message' => 'Salon not found. Please complete your salon profile first.',
                'error' => 'NO_SALON'
            ], 404);
        }

        // Use TODAY's data for preview (not yesterday!)
        $date = Carbon::today();

        try {
            $reportData = $this->reportService->generateReport($salon, $date);

            return response()->json([
                'report' => $reportData,
                'date' => $date->format('d.m.Y'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Greška pri generisanju pregleda izvještaja',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get monthly report data.
     */
    public function getMonthlyReport(Request $request)
    {
        $user = $request->user();

        if (!$user->isSalonOwner()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $salon = $user->ownedSalon;

        if (!$salon) {
            return response()->json([
                'message' => 'Salon not found. Please complete your salon profile first.',
                'error' => 'NO_SALON'
            ], 404);
        }

        // Get month from query parameter or use current month
        $monthInput = $request->query('month'); // Format: YYYY-MM
        $month = $monthInput ? Carbon::parse($monthInput . '-01') : Carbon::now();

        try {
            $reportData = $this->monthlyReportService->generateReport($salon, $month);

            return response()->json([
                'report' => $reportData,
                'month' => $month->format('m.Y'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Greška pri generisanju mjesečnog izvještaja',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send monthly report via email.
     */
    public function sendMonthlyReport(Request $request)
    {
        $user = $request->user();

        if (!$user->isSalonOwner()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $salon = $user->ownedSalon;

        if (!$salon) {
            return response()->json([
                'message' => 'Salon not found. Please complete your salon profile first.',
                'error' => 'NO_SALON'
            ], 404);
        }

        $settings = $salon->settings;

        // Get month from request or use previous month
        $monthInput = $request->input('month'); // Format: YYYY-MM
        $month = $monthInput ? Carbon::parse($monthInput . '-01') : Carbon::now()->subMonth();

        try {
            // Generate report data
            $reportData = $this->monthlyReportService->generateReport($salon, $month);

            // Determine email address
            $email = $settings?->daily_report_email ?? $salon->owner->email;

            // TODO: Create MonthlyReportMail class
            // For now, return the data
            // Mail::to($email)->send(new MonthlyReportMail($salon, $reportData, $month));

            return response()->json([
                'message' => "Mjesečni izvještaj za " . $month->locale('bs')->isoFormat('MMMM YYYY') . " je spreman",
                'email' => $email,
                'month' => $month->format('m.Y'),
                'report' => $reportData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Greška pri slanju mjesečnog izvještaja',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
