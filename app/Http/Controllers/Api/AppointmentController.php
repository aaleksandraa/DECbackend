<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Mail\AppointmentConfirmationMail;
use App\Models\Appointment;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Services\AppointmentService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AppointmentController extends Controller
{
    protected $appointmentService;
    protected $notificationService;

    public function __construct(
        AppointmentService $appointmentService,
        NotificationService $notificationService
    ) {
        $this->appointmentService = $appointmentService;
        $this->notificationService = $notificationService;
    }

    /**
     * Find or create a guest user by email.
     * If user with email exists, return that user.
     * Otherwise, create a new guest user that can later claim their appointments when they register.
     */
    private function findOrCreateGuestUser(array $data): ?\App\Models\User
    {
        // If no email provided, return null (appointment will be created without client_id)
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

        // Create new guest user
        return \App\Models\User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => bcrypt(\Illuminate\Support\Str::random(32)), // Random password
            'email_verified_at' => null,
            'role' => 'klijent',
            'is_guest' => true,
            'created_via' => 'booking',
        ]);
    }

    /**
     * Display a listing of the appointments for the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        if (!$user) {
        abort(401, 'Unauthorized');
    }

        $query = Appointment::query();

        if ($user->isClient()) {
            $query->where('client_id', $user->id);
        } elseif ($user->isStaff()) {
            $staffId = $user->staffProfile->id;
            $query->where('staff_id', $staffId);
        } elseif ($user->isSalonOwner()) {
            $salonId = $user->ownedSalon->id;
            $query->where('salon_id', $salonId);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date - convert European format (DD.MM.YYYY) to ISO format for database query
        if ($request->has('date')) {
            $dateInput = $request->date;
            // Check if date is in European format (DD.MM.YYYY)
            if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $dateInput)) {
                $dateInput = Carbon::createFromFormat('d.m.Y', $dateInput)->format('Y-m-d');
            }
            $query->whereDate('date', $dateInput);
        }

        // Filter by date range (for calendar views - much more efficient than loading all appointments)
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = $request->start_date;
            $endDate = $request->end_date;

            // Convert European format to ISO if needed
            if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $startDate)) {
                $startDate = Carbon::createFromFormat('d.m.Y', $startDate)->format('Y-m-d');
            }
            if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $endDate)) {
                $endDate = Carbon::createFromFormat('d.m.Y', $endDate)->format('Y-m-d');
            }

            $query->whereBetween('date', [$startDate, $endDate]);
        }

        // Filter by upcoming/past
        if ($request->has('type')) {
            if ($request->type === 'upcoming') {
                $query->upcoming();
            } elseif ($request->type === 'past') {
                $query->past();
            }
        }

        $appointments = $query->with(['salon', 'staff', 'service'])
            ->orderBy('date', 'desc')
            ->orderBy('time', 'desc')
            ->paginate($request->per_page ?? 15);

        return AppointmentResource::collection($appointments);
    }

    /**
     * Store a newly created appointment in storage.
     *
     * Uses database transaction and row locking to prevent race conditions
     * where two users might try to book the same time slot simultaneously.
     */
    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $user = $request->user();
        $isManualBooking = $user->role === 'salon' || $user->role === 'frizer';

        try {
            return DB::transaction(function () use ($request, $user, $isManualBooking) {
                // Lock the staff row to prevent concurrent booking
                $staff = Staff::where('id', $request->staff_id)
                    ->with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations', 'services'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $salon = Salon::findOrFail($request->salon_id);

                // Handle multi-service appointments
                $serviceIds = [];
                if ($request->has('services') && is_array($request->services)) {
                    // Multi-service booking
                    $serviceIds = array_column($request->services, 'id');
                } elseif ($request->has('service_id')) {
                    // Single service booking
                    $serviceIds = [$request->service_id];
                } else {
                    return response()->json([
                        'message' => 'Service ID or services array is required',
                    ], 422);
                }

                // Load all services and calculate total duration
                $services = Service::whereIn('id', $serviceIds)->get();
                $totalDuration = 0;
                $totalPrice = 0;
                $zeroServices = [];

                foreach ($services as $service) {
                    if ($service->duration == 0) {
                        $zeroServices[] = $service->name;
                    }
                    $totalDuration += $service->duration;
                    $totalPrice += $service->discount_price ?? $service->price;

                    // Check if staff can perform this service
                    if (!$staff->services->contains($service->id)) {
                        return response()->json([
                            'message' => "The selected staff cannot perform service: {$service->name}",
                        ], 422);
                    }
                }

                // VALIDATION: Prevent booking if ALL services have zero duration
                if ($totalDuration == 0) {
                    return response()->json([
                        'message' => 'Ne možete rezervisati usluge koje nemaju trajanje. Molimo odaberite drugu uslugu.',
                        'code' => 'ZERO_DURATION_SERVICE',
                        'services' => $zeroServices
                    ], 422);
                }

                // Check if the staff is available at the requested time
                if (!$this->appointmentService->isStaffAvailable($staff, $request->date, $request->time, $totalDuration)) {
                    return response()->json([
                        'message' => 'The selected staff is not available at the requested time',
                    ], 422);
                }

                // Calculate end time based on total duration
                $endTime = $this->appointmentService->calculateEndTime($request->time, $totalDuration);

                // For manual bookings, auto-confirm the appointment
                $initialStatus = $isManualBooking
                    ? 'confirmed'
                    : (($salon->auto_confirm || $staff->auto_confirm) ? 'confirmed' : 'pending');

                // Convert European date format to ISO format for database
                $dateForDb = Carbon::createFromFormat('d.m.Y', $request->date)->format('Y-m-d');

                // Determine client info based on booking type
                if ($isManualBooking) {
                    $guestUser = null;
                    if (!empty($request->client_email)) {
                        $guestUser = $this->findOrCreateGuestUser([
                            'name' => $request->client_name,
                            'email' => $request->client_email,
                            'phone' => $request->client_phone,
                        ]);
                    }

                    $clientId = $guestUser?->id;
                    $clientName = $request->client_name;
                    $clientEmail = $request->client_email;
                    $clientPhone = $request->client_phone;
                    $isGuest = true;
                    $guestAddress = $request->client_address;
                } else {
                    $clientId = $user->id;
                    $clientName = $user->name;
                    $clientEmail = $user->email;
                    $clientPhone = $user->phone;
                    $isGuest = false;
                    $guestAddress = null;
                }

                // Create ONE appointment with all services
                $appointment = Appointment::create([
                    'client_id' => $clientId,
                    'client_name' => $clientName,
                    'client_email' => $clientEmail,
                    'client_phone' => $clientPhone,
                    'is_guest' => $isGuest,
                    'guest_address' => $guestAddress,
                    'salon_id' => $salon->id,
                    'staff_id' => $staff->id,
                    'service_id' => count($serviceIds) === 1 ? $serviceIds[0] : null, // For backward compatibility
                    'service_ids' => $serviceIds, // Store all service IDs
                    'date' => $dateForDb,
                    'time' => $request->time,
                    'end_time' => $endTime,
                    'status' => $initialStatus,
                    'notes' => $request->notes,
                    'total_price' => $totalPrice,
                    'payment_status' => 'pending',
                ]);

                // Send notifications
                $this->notificationService->sendNewAppointmentNotifications($appointment);

                // Send confirmation email to client
                if ($clientEmail) {
                    Mail::to($clientEmail)->send(new AppointmentConfirmationMail($appointment));
                }

                return response()->json([
                    'message' => 'Appointment created successfully',
                    'appointment' => new AppointmentResource($appointment->load(['salon', 'staff', 'service'])),
                ], 201);
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'appointments_no_double_booking')) {
                Log::warning('Double booking attempt prevented', [
                    'user_id' => $user->id,
                    'staff_id' => $request->staff_id,
                    'date' => $request->date,
                    'time' => $request->time,
                ]);

                return response()->json([
                    'message' => 'This time slot has just been booked by another user. Please select a different time.',
                ], 422);
            }

            throw $e;
        }
    }

    /**
     * Display the specified appointment.
     */
    public function show(Appointment $appointment): AppointmentResource
    {
        $this->authorize('view', $appointment);

        $appointment->load(['salon', 'staff', 'service', 'review']);

        return new AppointmentResource($appointment);
    }

    /**
     * Update the specified appointment in storage.
     *
     * Uses database transaction and row locking when changing date/time/staff
     * to prevent race conditions.
     */
    public function update(UpdateAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('update', $appointment);

        // Convert date from European format if provided
        $dateForQuery = null;
        if ($request->has('date') && preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $request->date)) {
            $dateForQuery = Carbon::createFromFormat('d.m.Y', $request->date)->format('Y-m-d');
        }

        // If changing date/time/staff/service, check availability with locking
        if ($request->has('date') || $request->has('time') || $request->has('staff_id') || $request->has('service_id')) {
            try {
                return DB::transaction(function () use ($request, $appointment, $dateForQuery) {
                    $date = $dateForQuery ?? $request->date ?? $appointment->date;
                    $time = $request->time ?? $appointment->time;
                    $staffId = $request->staff_id ?? $appointment->staff_id;
                    $serviceId = $request->service_id ?? $appointment->service_id;

                    // Lock the staff row to prevent concurrent booking
                    $staff = Staff::where('id', $staffId)
                        ->with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations', 'services'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    $service = Service::findOrFail($serviceId);

                    // Check if the staff can perform this service
                    if (!$staff->services->contains($service->id)) {
                        return response()->json([
                            'message' => 'The selected staff cannot perform this service',
                        ], 422);
                    }

                    // Check if the staff is available at the requested time (excluding this appointment)
                    if (!$this->appointmentService->isStaffAvailable($staff, $date, $time, $service->duration, $appointment->id)) {
                        return response()->json([
                            'message' => 'The selected staff is not available at the requested time',
                        ], 422);
                    }

                    // Build update data
                    $updateData = $request->validated();

                    // Replace date with converted format if needed
                    if ($dateForQuery) {
                        $updateData['date'] = $dateForQuery;
                    }

                    // Calculate end time if time or service changed
                    if ($request->has('time') || $request->has('service_id')) {
                        $updateData['end_time'] = $this->appointmentService->calculateEndTime($time, $service->duration);

                        // Update total price if service changed
                        if ($request->has('service_id')) {
                            $updateData['total_price'] = $service->discount_price ?? $service->price;
                        }
                    }

                    $oldStatus = $appointment->status;
                    $appointment->update($updateData);

                    // Send notifications if status changed
                    if ($request->has('status') && $oldStatus !== $request->status) {
                        $this->notificationService->sendAppointmentStatusChangeNotifications($appointment, $oldStatus);
                    }

                    return response()->json([
                        'message' => 'Appointment updated successfully',
                        'appointment' => new AppointmentResource($appointment->load(['salon', 'staff', 'service'])),
                    ]);
                });
            } catch (QueryException $e) {
                // Check if this is a unique constraint violation
                if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'appointments_no_double_booking')) {
                    Log::warning('Double booking attempt prevented on update', [
                        'appointment_id' => $appointment->id,
                        'staff_id' => $request->staff_id ?? $appointment->staff_id,
                        'date' => $request->date ?? $appointment->date,
                        'time' => $request->time ?? $appointment->time,
                    ]);

                    return response()->json([
                        'message' => 'This time slot has just been booked by another user. Please select a different time.',
                    ], 422);
                }

                throw $e;
            }
        }

        // If not changing scheduling fields, just update directly
        $oldStatus = $appointment->status;
        $appointment->update($request->validated());

        // Send notifications if status changed
        if ($request->has('status') && $oldStatus !== $request->status) {
            $this->notificationService->sendAppointmentStatusChangeNotifications($appointment, $oldStatus);
        }

        return response()->json([
            'message' => 'Appointment updated successfully',
            'appointment' => new AppointmentResource($appointment->load(['salon', 'staff', 'service'])),
        ]);
    }

    /**
     * Remove the specified appointment from storage.
     */
    public function destroy(Appointment $appointment): JsonResponse
    {
        $this->authorize('delete', $appointment);

        $appointment->delete();

        return response()->json([
            'message' => 'Appointment deleted successfully',
        ]);
    }

    /**
     * Cancel the specified appointment.
     */
    public function cancel(Appointment $appointment): JsonResponse
    {
        $this->authorize('cancel', $appointment);

        if (!$appointment->canBeCancelled()) {
            return response()->json([
                'message' => 'This appointment cannot be cancelled',
            ], 422);
        }

        $oldStatus = $appointment->status;
        $appointment->update(['status' => 'cancelled']);

        // Send notifications
        $this->notificationService->sendAppointmentStatusChangeNotifications($appointment, $oldStatus);

        return response()->json([
            'message' => 'Appointment cancelled successfully',
            'appointment' => new AppointmentResource($appointment->load(['salon', 'staff', 'service'])),
        ]);
    }

    /**
     * Mark the specified appointment as no-show (client didn't show up).
     * Only staff or salon owner can mark an appointment as no-show.
     */
    public function markNoShow(Appointment $appointment): JsonResponse
    {
        $this->authorize('update', $appointment);

        if (!$appointment->canBeMarkedAsNoShow()) {
            return response()->json([
                'message' => 'This appointment cannot be marked as no-show. It must be a confirmed appointment that has passed its start time.',
            ], 422);
        }

        $oldStatus = $appointment->status;
        $appointment->update(['status' => 'no_show']);

        // Send notifications
        $this->notificationService->sendAppointmentStatusChangeNotifications($appointment, $oldStatus);

        Log::info('Appointment marked as no-show', [
            'appointment_id' => $appointment->id,
            'marked_by' => request()->user()->id,
        ]);

        return response()->json([
            'message' => 'Appointment marked as no-show',
            'appointment' => new AppointmentResource($appointment->load(['salon', 'staff', 'service'])),
        ]);
    }

    /**
     * Mark the specified appointment as completed manually.
     * Only staff or salon owner can mark an appointment as completed.
     */
    public function markCompleted(Appointment $appointment): JsonResponse
    {
        $this->authorize('update', $appointment);

        if (!in_array($appointment->status, ['confirmed', 'in_progress'])) {
            return response()->json([
                'message' => 'Only confirmed or in-progress appointments can be marked as completed.',
            ], 422);
        }

        $oldStatus = $appointment->status;
        $appointment->update(['status' => 'completed']);

        // Send notifications
        $this->notificationService->sendAppointmentStatusChangeNotifications($appointment, $oldStatus);

        return response()->json([
            'message' => 'Appointment marked as completed',
            'appointment' => new AppointmentResource($appointment->load(['salon', 'staff', 'service'])),
        ]);
    }

    /**
     * Get calendar capacity for a month.
     * Returns capacity data for each day in the specified month.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getMonthCapacity(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthorized');
        }

        // Validate month parameter (YYYY-MM format)
        $month = $request->query('month');
        if (!$month || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            return response()->json([
                'message' => 'Invalid month format. Use YYYY-MM format.',
            ], 422);
        }

        // Parse month
        [$year, $monthNum] = explode('-', $month);
        $startDate = Carbon::create($year, $monthNum, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Determine salon and staff based on user role
        $salonId = null;
        $staffId = null;

        if ($user->isSalonOwner()) {
            $salonId = $user->ownedSalon->id;
        } elseif ($user->isStaff()) {
            $salonId = $user->staffProfile->salon_id;
            $staffId = $user->staffProfile->id;
        } else {
            return response()->json([
                'message' => 'Unauthorized. Only salon owners and staff can access capacity data.',
            ], 403);
        }

        // Get all appointments for the month
        $query = Appointment::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('salon_id', $salonId)
            ->whereIn('status', ['confirmed', 'in_progress', 'completed']);

        if ($staffId) {
            $query->where('staff_id', $staffId);
        }

        $appointments = $query->get();

        // Get working hours
        $workingHours = $this->getWorkingHours($user, $staffId);

        // Calculate capacity for each day
        $capacityData = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayAppointments = $appointments->filter(function ($app) use ($dateStr) {
                return $app->date === $dateStr;
            });

            // Calculate total slots (30-minute slots)
            $totalSlots = $this->calculateTotalSlots($workingHours['start'], $workingHours['end']);
            $occupiedSlots = $dayAppointments->count();
            $freeSlots = max(0, $totalSlots - $occupiedSlots);
            $percentage = $totalSlots > 0 ? round(($occupiedSlots / $totalSlots) * 100) : 0;

            // Determine status and color
            if ($percentage >= 100) {
                $status = 'full';
                $color = 'red';
            } elseif ($percentage >= 70) {
                $status = 'busy';
                $color = 'yellow';
            } elseif ($percentage > 0) {
                $status = 'available';
                $color = 'green';
            } else {
                $status = 'empty';
                $color = 'gray';
            }

            $capacityData[] = [
                'date' => $dateStr,
                'total_slots' => $totalSlots,
                'occupied_slots' => $occupiedSlots,
                'free_slots' => $freeSlots,
                'percentage' => $percentage,
                'status' => $status,
                'color' => $color,
            ];

            $currentDate->addDay();
        }

        return response()->json([
            'month' => $month,
            'capacity' => $capacityData,
        ]);
    }

    /**
     * Get working hours for salon or staff
     */
    private function getWorkingHours($user, $staffId = null): array
    {
        if ($staffId) {
            $staff = Staff::find($staffId);
            if ($staff && $staff->working_hours) {
                $hours = $staff->working_hours;
                $earliestStart = 24;
                $latestEnd = 0;

                foreach ($hours as $day) {
                    if (isset($day['is_working']) && $day['is_working'] && isset($day['start']) && isset($day['end'])) {
                        $startHour = (int) explode(':', $day['start'])[0];
                        $endHour = (int) explode(':', $day['end'])[0];
                        if ($startHour < $earliestStart) $earliestStart = $startHour;
                        if ($endHour > $latestEnd) $latestEnd = $endHour;
                    }
                }

                if ($earliestStart < 24 && $latestEnd > 0) {
                    return ['start' => $earliestStart, 'end' => $latestEnd];
                }
            }
        }

        // Use salon working hours
        if ($user->isSalonOwner() && $user->ownedSalon->working_hours) {
            $hours = $user->ownedSalon->working_hours;
            $earliestStart = 24;
            $latestEnd = 0;

            foreach ($hours as $day) {
                if (isset($day['is_open']) && $day['is_open'] && isset($day['open']) && isset($day['close'])) {
                    $startHour = (int) explode(':', $day['open'])[0];
                    $endHour = (int) explode(':', $day['close'])[0];
                    if ($startHour < $earliestStart) $earliestStart = $startHour;
                    if ($endHour > $latestEnd) $latestEnd = $endHour;
                }
            }

            if ($earliestStart < 24 && $latestEnd > 0) {
                return ['start' => $earliestStart, 'end' => $latestEnd];
            }
        }

        // Default working hours
        return ['start' => 8, 'end' => 20];
    }

    /**
     * Calculate total available slots based on working hours
     */
    private function calculateTotalSlots(int $startHour, int $endHour): int
    {
        $startMinutes = $startHour * 60;
        $endMinutes = $endHour * 60;
        $totalMinutes = $endMinutes - $startMinutes;

        // Each slot is 30 minutes
        return (int) floor($totalMinutes / 30);
    }

    /**
     * Get calendar capacity for a month (PUBLIC - for guest booking)
     *
     * @param Request $request
     * @param int $salonId
     * @return JsonResponse
     */
    public function getPublicMonthCapacity(Request $request, $salonId): JsonResponse
    {
        // Validate month parameter (YYYY-MM format)
        $month = $request->query('month');
        if (!$month || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            return response()->json([
                'message' => 'Invalid month format. Use YYYY-MM format.',
            ], 422);
        }

        // Parse month
        [$year, $monthNum] = explode('-', $month);
        $startDate = Carbon::create($year, $monthNum, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Get all appointments for the month for this salon
        $appointments = Appointment::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('salon_id', $salonId)
            ->whereIn('status', ['confirmed', 'in_progress', 'completed'])
            ->get();

        // Get salon working hours
        $salon = Salon::find($salonId);
        if (!$salon) {
            return response()->json([
                'message' => 'Salon not found.',
            ], 404);
        }

        $workingHours = ['start' => 8, 'end' => 20]; // Default
        if ($salon->working_hours) {
            $hours = $salon->working_hours;
            $earliestStart = 24;
            $latestEnd = 0;

            foreach ($hours as $day) {
                if (isset($day['is_open']) && $day['is_open'] && isset($day['open']) && isset($day['close'])) {
                    $startHour = (int) explode(':', $day['open'])[0];
                    $endHour = (int) explode(':', $day['close'])[0];
                    if ($startHour < $earliestStart) $earliestStart = $startHour;
                    if ($endHour > $latestEnd) $latestEnd = $endHour;
                }
            }

            if ($earliestStart < 24 && $latestEnd > 0) {
                $workingHours = ['start' => $earliestStart, 'end' => $latestEnd];
            }
        }

        // Calculate capacity for each day
        $capacityData = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayAppointments = $appointments->filter(function ($app) use ($dateStr) {
                return $app->date === $dateStr;
            });

            // Calculate total slots (30-minute slots)
            $totalSlots = $this->calculateTotalSlots($workingHours['start'], $workingHours['end']);
            $occupiedSlots = $dayAppointments->count();
            $freeSlots = max(0, $totalSlots - $occupiedSlots);
            $percentage = $totalSlots > 0 ? round(($occupiedSlots / $totalSlots) * 100) : 0;

            // Determine status and color
            if ($percentage >= 100) {
                $status = 'full';
                $color = 'red';
            } elseif ($percentage >= 70) {
                $status = 'busy';
                $color = 'yellow';
            } elseif ($percentage > 0) {
                $status = 'available';
                $color = 'green';
            } else {
                $status = 'empty';
                $color = 'gray';
            }

            $capacityData[] = [
                'date' => $dateStr,
                'total_slots' => $totalSlots,
                'occupied_slots' => $occupiedSlots,
                'free_slots' => $freeSlots,
                'percentage' => $percentage,
                'status' => $status,
                'color' => $color,
            ];

            $currentDate->addDay();
        }

        return response()->json([
            'month' => $month,
            'capacity' => $capacityData,
        ]);
    }

    /**
     * Generate and download ICS calendar file for an appointment.
     * Public endpoint - no authentication required.
     *
     * @param int $appointmentId
     * @return \Illuminate\Http\Response
     */
    public function downloadIcs($appointmentId)
    {
        try {
            // Find appointment by ID
            $appointment = Appointment::with(['salon', 'staff', 'service'])->findOrFail($appointmentId);

            // Generate ICS content
            $icsContent = $this->generateIcsContent($appointment);

            // Return as downloadable file
            return response($icsContent, 200)
                ->header('Content-Type', 'text/calendar; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="termin-' . $appointment->id . '.ics"');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Termin nije pronađen',
            ], 404);
        } catch (\Exception $e) {
            Log::error('ICS download error', [
                'appointment_id' => $appointmentId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Greška pri generisanju ICS fajla',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate ICS file content for an appointment.
     *
     * @param Appointment $appointment
     * @return string
     */
    private function generateIcsContent(Appointment $appointment): string
    {
        // Parse date and time - handle both string and Carbon object
        if ($appointment->date instanceof \Carbon\Carbon) {
            $dateStr = $appointment->date->format('Y-m-d');
        } else {
            $dateStr = $appointment->date;
        }

        // Create Carbon instances for start and end times
        $startTime = Carbon::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $appointment->time);

        // Handle end_time which might have seconds (HH:MM:SS)
        $endTimeStr = substr($appointment->end_time, 0, 5); // Get HH:MM only
        $endTime = Carbon::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $endTimeStr);

        // Format dates for ICS (local time format)
        $dtStamp = Carbon::now()->format('Ymd\THis\Z');
        $dtStart = $startTime->format('Ymd\THis');
        $dtEnd = $endTime->format('Ymd\THis');

        // Build description
        $description = $appointment->service->name;
        if ($appointment->notes) {
            $description .= '\\n\\nNapomene: ' . $this->escapeIcsText($appointment->notes);
        }
        $description .= '\\n\\nFrizer: ' . $appointment->staff->name;
        $description .= '\\nCijena: ' . number_format($appointment->total_price, 2) . ' KM';

        // Build location
        $location = $appointment->salon->name;
        if ($appointment->salon->address) {
            $location .= ', ' . $appointment->salon->address;
        }
        if ($appointment->salon->city) {
            $location .= ', ' . $appointment->salon->city;
        }

        // Build summary
        $summary = $appointment->service->name . ' - ' . $appointment->salon->name;

        // Generate UID
        $uid = 'appointment-' . $appointment->id . '@frizerino.com';

        // Build ICS content
        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//Frizerino//Appointment//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:" . $uid . "\r\n";
        $ics .= "DTSTAMP:" . $dtStamp . "\r\n";
        $ics .= "DTSTART:" . $dtStart . "\r\n";
        $ics .= "DTEND:" . $dtEnd . "\r\n";
        $ics .= "SUMMARY:" . $this->escapeIcsText($summary) . "\r\n";
        $ics .= "DESCRIPTION:" . $description . "\r\n";
        $ics .= "LOCATION:" . $this->escapeIcsText($location) . "\r\n";
        $ics .= "STATUS:CONFIRMED\r\n";

        // Add reminder (1 hour before)
        $ics .= "BEGIN:VALARM\r\n";
        $ics .= "TRIGGER:-PT1H\r\n";
        $ics .= "ACTION:DISPLAY\r\n";
        $ics .= "DESCRIPTION:Podsjetnik: " . $this->escapeIcsText($summary) . " za 1 sat\r\n";
        $ics .= "END:VALARM\r\n";

        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }

    /**
     * Escape text for ICS format.
     *
     * @param string $text
     * @return string
     */
    private function escapeIcsText(string $text): string
    {
        // Escape special characters for ICS format
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(',', '\\,', $text);
        $text = str_replace(';', '\\;', $text);
        $text = str_replace("\n", '\\n', $text);
        $text = str_replace("\r", '', $text);

        return $text;
    }
}
