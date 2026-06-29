<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exceptions\BookingConflictException;
use App\Exceptions\BookingUnavailableException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Salon;
use App\Models\WidgetSetting;
use App\Models\WidgetAnalytics;
use App\Models\Appointment;
use App\Models\Staff;
use App\Services\BookingService;
use App\Services\SalonService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WidgetController extends Controller
{
    protected NotificationService $notificationService;
    protected BookingService $bookingService;
    protected SalonService $salonService;

    public function __construct(
        NotificationService $notificationService,
        BookingService $bookingService,
        SalonService $salonService,
    ) {
        $this->notificationService = $notificationService;
        $this->bookingService = $bookingService;
        $this->salonService = $salonService;
    }

    /**
     * Find or create a guest user by email.
     * If user with email exists, return that user.
     * Otherwise, create a new guest user that can later claim their appointments when they register.
     */
    private function findOrCreateGuestUser(array $data): ?\App\Models\User
    {
        // If no email provided, return null
        if (empty($data['email'])) {
            return null;
        }

        // Try to find existing user by email
        $user = \App\Models\User::where('email', $data['email'])->first();

        if ($user) {
            // User exists - update info if provided data is more complete
            $updates = [];

            if (!empty($data['name']) && strlen($data['name']) > strlen($user->name)) {
                $updates['name'] = $data['name'];
            }

            if (!empty($data['phone']) && $user->phone !== $data['phone']) {
                $updates['phone'] = $data['phone'];
            }

            if (!empty($updates)) {
                $user->update($updates);
            }

            return $user;
        }

        // Create new guest user. role is set explicitly (not mass assignable).
        $guest = new \App\Models\User([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => bcrypt(\Illuminate\Support\Str::random(32)),
            'is_guest' => true,
            'created_via' => 'widget',
        ]);
        $guest->role = 'klijent';
        $guest->save();

        return $guest;
    }

    /**
     * Get widget data (salon, services, staff)
     */
    public function show(Request $request, string $salonSlug): JsonResponse
    {
        // FIXED: Standardized to api_key
        $apiKey = $request->query('api_key') ?? $request->query('key');

        if (!$apiKey) {
            return response()->json(['error' => 'API key is required'], 401);
        }

        // First, try to find widget by API key only (for debugging)
        $widgetByKey = WidgetSetting::where('api_key', $apiKey)->first();

        if (!$widgetByKey) {
            Log::warning('Widget API: API key not found in database', [
                'api_key_prefix' => substr($apiKey, 0, 20) . '...',
                'salon_slug' => $salonSlug,
            ]);
            return response()->json(['error' => 'Invalid API key - not found'], 401);
        }

        // Check if widget is active
        if (!$widgetByKey->is_active) {
            Log::warning('Widget API: Widget is inactive', [
                'widget_id' => $widgetByKey->id,
                'is_active' => $widgetByKey->is_active,
                'is_active_type' => gettype($widgetByKey->is_active),
            ]);
            return response()->json(['error' => 'Widget is inactive'], 401);
        }

        $widgetSetting = $widgetByKey;

        $referer = $request->headers->get('referer');
        $domain = $referer ? parse_url($referer, PHP_URL_HOST) : null;

        if (!$widgetSetting->isDomainAllowed($domain)) {
            $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_ERROR, $request, [
                'error' => 'Domain not allowed',
                'domain' => $domain,
            ], $widgetSetting->id);
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        // Sort services by display_order, staff by display_order
        // FIXED: Use whereRaw for BOOLEAN columns (PostgreSQL strict typing)
        $salon = Salon::with(['services' => function($query) {
            $query->whereRaw('is_active = true')
                  ->orderBy('display_order')
                  ->orderBy('id');
        }, 'staff' => function($query) {
            $query->whereRaw('is_active = true')
                  ->orderBy('display_order')
                  ->orderBy('name');
        }])
            ->where('slug', $salonSlug)
            ->where('id', $widgetSetting->salon_id)
            ->where('status', 'approved')
            ->first();

        if (!$salon) {
            $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_ERROR, $request, [
                'error' => 'Salon not found',
                'slug' => $salonSlug,
            ], $widgetSetting->id);
            return response()->json(['error' => 'Salon not found or inactive'], 404);
        }

        $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_VIEW, $request, [], $widgetSetting->id);
        $widgetSetting->update(['last_used_at' => now()]);

        return response()->json([
            'salon' => [
                'id' => $salon->id,
                'name' => $salon->name,
                'slug' => $salon->slug,
                'description' => $salon->description,
                'address' => $salon->address,
                'city' => $salon->city,
                'phone' => $salon->phone,
                'email' => $salon->email,
                'working_hours' => $salon->working_hours,
                'category_order' => $salon->category_order,
                'images' => $salon->images,
            ],
            'services' => $salon->services->map(function($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'price' => (float) $service->price,
                    'discount_price' => $service->discount_price ? (float) $service->discount_price : null,
                    'duration' => (int) $service->duration, // Ensure integer for frontend validation
                    'category' => $service->category,
                    'display_order' => $service->display_order,
                    'staff_ids' => $service->staff_ids,
                ];
            }),
            'staff' => $salon->staff->map(function($staff) {
                $avatarUrl = null;
                if ($staff->avatar) {
                    $avatarUrl = str_starts_with($staff->avatar, 'http')
                        ? $staff->avatar
                        : config('app.url') . '/storage/' . $staff->avatar;
                }
                return [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'role' => $staff->role,
                    'avatar' => $avatarUrl,
                    'bio' => $staff->bio,
                    'rating' => $staff->rating,
                    'review_count' => $staff->review_count,
                ];
            }),
            'theme' => $widgetSetting->getMergedTheme(),
            'settings' => $widgetSetting->settings ?? [],
        ]);
    }

    /**
     * Get available time slots for multiple services
     */
    public function availableSlots(Request $request): JsonResponse
    {
        // FIXED: Standardized to api_key
        $apiKey = $request->input('api_key') ?? $request->input('key');

        // FIXED: Use whereRaw for BOOLEAN columns (PostgreSQL strict typing)
        $widgetSetting = WidgetSetting::where('api_key', $apiKey)
            ->whereRaw('is_active = true')
            ->first();

        if (!$widgetSetting) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        $referer = $request->headers->get('referer');
        $domain = $referer ? parse_url($referer, PHP_URL_HOST) : null;

        if (!$widgetSetting->isDomainAllowed($domain)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|integer|exists:staff,id',
            'date' => ['required', 'regex:/^\d{2}\.\d{2}\.\d{4}$/'],
            'services' => 'required|array|min:1',
            'services.*.serviceId' => 'required|exists:services,id',
            'services.*.duration' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $staffId = $request->input('staff_id');
        $staff = Staff::findOrFail($staffId);

        if ($staff->salon_id != $widgetSetting->salon_id) {
            return response()->json(['error' => 'Invalid staff for this salon'], 403);
        }

        try {
            $servicesData = $this->salonService->resolveSlotServicesFromDatabase(
                $widgetSetting->salon_id,
                array_map(function ($service) use ($staffId) {
                    return [
                        'serviceId' => $service['serviceId'],
                        'staffId' => $staffId,
                    ];
                }, $request->input('services'))
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $salon = Salon::findOrFail($widgetSetting->salon_id);

        $slots = $this->salonService->getAvailableTimeSlotsForMultipleServices(
            $salon,
            $request->input('date'),
            $servicesData
        );

        return response()->json(['slots' => $slots]);
    }

    /**
     * Get available dates for a month (dates that have at least one available slot)
     */
    public function availableDates(Request $request): JsonResponse
    {
        // FIXED: Standardized to api_key
        $apiKey = $request->input('api_key') ?? $request->input('key');

        // FIXED: Use whereRaw for BOOLEAN columns (PostgreSQL strict typing)
        $widgetSetting = WidgetSetting::where('api_key', $apiKey)
            ->whereRaw('is_active = true')
            ->first();

        if (!$widgetSetting) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        $referer = $request->headers->get('referer');
        $domain = $referer ? parse_url($referer, PHP_URL_HOST) : null;

        if (!$widgetSetting->isDomainAllowed($domain)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|integer|exists:staff,id',
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'], // YYYY-MM format
            'services' => 'required|array|min:1',
            'services.*.serviceId' => 'required|exists:services,id',
            'services.*.duration' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $staffId = $request->input('staff_id');
        $staff = Staff::findOrFail($staffId);

        if ($staff->salon_id != $widgetSetting->salon_id) {
            return response()->json(['error' => 'Invalid staff for this salon'], 403);
        }

        try {
            $servicesData = $this->salonService->resolveSlotServicesFromDatabase(
                $widgetSetting->salon_id,
                array_map(function ($service) use ($staffId) {
                    return [
                        'serviceId' => $service['serviceId'],
                        'staffId' => $staffId,
                    ];
                }, $request->input('services'))
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $salon = Salon::findOrFail($widgetSetting->salon_id);

        // Parse month
        $monthStr = $request->input('month');
        $year = (int) substr($monthStr, 0, 4);
        $month = (int) substr($monthStr, 5, 2);

        // Get first and last day of month
        $firstDay = Carbon::createFromDate($year, $month, 1);
        $lastDay = $firstDay->copy()->endOfMonth();
        $today = Carbon::today();

        $availableDates = [];
        $unavailableDates = [];

        // Check each day in the month
        for ($day = $firstDay->copy(); $day <= $lastDay; $day->addDay()) {
            $dateStr = $day->format('d.m.Y');
            $isoDate = $day->format('Y-m-d');

            // Skip past dates
            if ($day < $today) {
                $unavailableDates[] = $isoDate;
                continue;
            }

            // Check if salon is open on this day
            $dayOfWeek = strtolower($day->format('l'));
            $salonHours = $salon->getNormalizedDayHours($dayOfWeek);
            if (!$salonHours) {
                $unavailableDates[] = $isoDate;
                continue;
            }

            // Check if staff is working on this day
            $staffHours = $staff->working_hours[$dayOfWeek] ?? null;
            if (!$staffHours || !$staffHours['is_working']) {
                $unavailableDates[] = $isoDate;
                continue;
            }

            // Get available slots for this day
            $slots = $this->salonService->getAvailableTimeSlotsForMultipleServices(
                $salon,
                $dateStr,
                $servicesData
            );

            if (count($slots) > 0) {
                $availableDates[] = $isoDate;
            } else {
                $unavailableDates[] = $isoDate;
            }
        }

        return response()->json([
            'available_dates' => $availableDates,
            'unavailable_dates' => $unavailableDates,
            'month' => $monthStr,
        ]);
    }

    /**
     * Book appointment(s) via widget
     */
    public function book(Request $request): JsonResponse
    {
        // FIXED: Standardized to api_key (with fallback for backward compatibility)
        $apiKey = $request->input('api_key') ?? $request->input('key');

        // FIXED: Use whereRaw for BOOLEAN columns (PostgreSQL strict typing)
        $widgetSetting = WidgetSetting::where('api_key', $apiKey)
            ->whereRaw('is_active = true')
            ->first();

        if (!$widgetSetting) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        // Domain check. isDomainAllowed() allows all when no whitelist is set,
        // but enforces it (including denying a missing referer) once configured.
        $referer = $request->headers->get('referer');
        $domain = $referer ? parse_url($referer, PHP_URL_HOST) : null;

        if (!$widgetSetting->isDomainAllowed($domain)) {
            $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_ERROR, $request, [
                'error' => 'Domain not allowed',
                'domain' => $domain,
            ], $widgetSetting->id);
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        $validator = Validator::make($request->all(), [
            'salon_id' => 'required|integer|exists:salons,id',
            'staff_id' => 'required|integer|exists:staff,id',
            'date' => ['required', 'regex:/^\d{2}\.\d{2}\.\d{4}$/'],
            'time' => 'required|date_format:H:i',
            'guest_name' => 'required|string|max:255|min:3',
            'guest_email' => 'nullable|email|max:255',
            'guest_phone' => 'required|string|min:8|max:20',
            'guest_address' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'service_id' => 'required_without:services|integer|exists:services,id',
            'services' => 'required_without:service_id|array|min:1',
            'services.*.id' => 'required_with:services|exists:services,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->input('salon_id') != $widgetSetting->salon_id) {
            $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_ERROR, $request, [
                'error' => 'Invalid salon',
            ], $widgetSetting->id);
            return response()->json(['error' => 'Invalid salon'], 403);
        }

        $staff = Staff::findOrFail($request->input('staff_id'));
        if ($staff->salon_id != $widgetSetting->salon_id) {
            return response()->json(['error' => 'Invalid staff for this salon'], 403);
        }

        try {
            $appointment = $this->bookingService->createAppointment($request->all(), [
                'booking_source' => 'widget',
                'is_guest' => true,
                'create_guest_user' => true,
                'idempotency_key' => $request->header('Idempotency-Key') ?: $request->input('idempotency_key'),
            ]);

            $serviceIds = $appointment->service_ids ?: array_filter([$appointment->service_id]);

            if ($appointment->wasRecentlyCreated) {
                $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_BOOKING, $request, [
                    'appointment_id' => $appointment->id,
                    'service_ids' => $serviceIds,
                    'staff_id' => $appointment->staff_id,
                    'total_price' => $appointment->total_price,
                ], $widgetSetting->id);

                $widgetSetting->increment('total_bookings');
            }

            return response()->json([
                'success' => true,
                'message' => 'Rezervacija uspjesno kreirana',
                'appointment' => [
                    'id' => $appointment->id,
                    'date' => Carbon::parse($appointment->date)->format('d.m.Y'),
                    'time' => $appointment->time,
                    'end_time' => $appointment->end_time,
                    'status' => $appointment->status,
                ],
                'total_price' => $appointment->total_price,
                'status' => $appointment->status,
            ], 201);
        } catch (BookingConflictException $e) {
            Log::warning('Double booking attempt prevented (widget)', [
                'guest_name' => $request->input('guest_name'),
                'staff_id' => $request->input('staff_id'),
                'date' => $request->input('date'),
                'time' => $request->input('time'),
            ]);

            return response()->json([
                'error' => 'Zao nam je, neko se u medjuvremenu zakazao u to vrijeme. Molimo odaberite drugo vrijeme.',
                'code' => $e->reasonCode(),
                'reason_code' => $e->reasonCode(),
                'redirect_to_time' => true
            ], 409);
        } catch (BookingUnavailableException $e) {
            $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_ERROR, $request, [
                'error' => $e->getMessage(),
                'reason_code' => $e->reasonCode(),
            ], $widgetSetting->id);

            return response()->json([
                'error' => $e->getMessage(),
                'code' => $e->reasonCode(),
                'reason_code' => $e->reasonCode(),
                'redirect_to_time' => true
            ], 422);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            Log::warning('Widget booking request rejected', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]);

            $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_ERROR, $request, [
                'error' => $e->getMessage(),
            ], $widgetSetting->id);

            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }

    }

    /**
     * Log analytics event
     */
    private function logAnalytics(?int $salonId, string $eventType, Request $request, array $metadata = [], ?int $widgetSettingId = null): void
    {
        try {
            $referer = $request->headers->get('referer');
            $domain = $referer ? parse_url($referer, PHP_URL_HOST) : null;

            WidgetAnalytics::create([
                'salon_id' => $salonId,
                'widget_setting_id' => $widgetSettingId,
                'event_type' => $eventType,
                'referrer_domain' => $domain,
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 500),
                'metadata' => $metadata,
            ]);
        } catch (\Exception $e) {
            Log::warning('Widget analytics log failed: ' . $e->getMessage());
        }
    }
}
