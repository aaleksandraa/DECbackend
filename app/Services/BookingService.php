<?php

namespace App\Services;

use App\Exceptions\BookingConflictException;
use App\Exceptions\BookingUnavailableException;
use App\Mail\AppointmentConfirmationMail;
use App\Models\Appointment;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BookingService
{
    public const BLOCKING_STATUSES = Appointment::BLOCKING_STATUSES;

    public function __construct(
        private AppointmentService $appointmentService,
        private NotificationService $notificationService,
    ) {}

    /**
     * Get available time slots for a salon/service/date combination.
     */
    public function getAvailability(int $salonId, int $serviceId, string $date): array
    {
        $service = Service::where('salon_id', $salonId)->findOrFail($serviceId);
        $normalizedDate = $this->normalizeDate($date);

        $staffMembers = Staff::where('salon_id', $salonId)
            ->whereRaw('is_active = true')
            ->whereRaw('accepts_bookings = true')
            ->whereHas('services', function ($query) use ($serviceId) {
                $query->where('services.id', $serviceId);
            })
            ->with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations'])
            ->get();

        $allSlots = [];
        foreach ($staffMembers as $staff) {
            $slots = $this->appointmentService->getAvailableSlots($staff, $normalizedDate, (int) $service->duration);
            $allSlots = array_merge($allSlots, $slots);
        }

        $allSlots = array_values(array_unique($allSlots));
        sort($allSlots);

        return [
            'slots' => $allSlots,
            'staff_count' => $staffMembers->count(),
        ];
    }

    /**
     * Central create path for web, manual, public, widget and chatbot bookings.
     */
    public function createAppointment(array $data, array $options = []): Appointment
    {
        $salonId = (int) ($data['salon_id'] ?? 0);
        if ($salonId <= 0) {
            throw new \InvalidArgumentException('salon_id is required');
        }

        $serviceIds = $this->resolveServiceIds($data);
        if (empty($serviceIds)) {
            throw new \InvalidArgumentException('service_id or services array is required');
        }

        $dateForDb = $this->normalizeDate((string) ($data['date'] ?? ''));
        $time = $this->normalizeTime((string) ($data['time'] ?? ''));
        $this->assertBookableDateTime($dateForDb, $time, (bool) ($options['allow_past'] ?? false));

        $bookingSource = (string) ($options['booking_source'] ?? $data['booking_source'] ?? 'web');
        $idempotencyKey = $this->normalizeIdempotencyKey($options['idempotency_key'] ?? $data['idempotency_key'] ?? null);
        $isManualBooking = (bool) ($options['is_manual'] ?? false);
        $isGuest = (bool) ($options['is_guest'] ?? false);
        $enforceAcceptsBookings = (bool) ($options['enforce_accepts_bookings'] ?? !$isManualBooking);

        if ($idempotencyKey) {
            $existingAppointment = $this->findAppointmentByIdempotencyKey($salonId, $bookingSource, $idempotencyKey);
            if ($existingAppointment) {
                return $existingAppointment;
            }
        }

        $result = DB::transaction(function () use (
            $data,
            $salonId,
            $serviceIds,
            $dateForDb,
            $time,
            $bookingSource,
            $idempotencyKey,
            $isManualBooking,
            $isGuest,
            $enforceAcceptsBookings,
            $options
        ) {
            $salon = Salon::lockForUpdate()->findOrFail($salonId);
            if ($idempotencyKey) {
                $existingAppointment = $this->findAppointmentByIdempotencyKey($salon->id, $bookingSource, $idempotencyKey, true);
                if ($existingAppointment) {
                    return ['appointment' => $existingAppointment, 'created' => false];
                }
            }

            [$services, $totalDuration, $totalPrice] = $this->loadServicesForSalon($salon->id, $serviceIds);

            $staffId = isset($data['staff_id'])
                ? (int) $data['staff_id']
                : (int) $this->findAvailableStaff($salon->id, $serviceIds, $dateForDb, $time, $totalDuration);

            if ($staffId <= 0) {
                throw new BookingConflictException('No available staff member for the selected slot');
            }

            $staff = $this->loadStaffForBooking($salon->id, $staffId);
            $this->assertStaffCanBeBooked($staff, $serviceIds, $enforceAcceptsBookings);

            $reasonCode = $this->appointmentService->getStaffUnavailabilityReason($staff, $dateForDb, $time, $totalDuration);
            if ($reasonCode !== null) {
                if ($reasonCode === 'TIME_SLOT_TAKEN' || $this->hasBlockingOverlap($staff, $dateForDb, $time, $totalDuration)) {
                    throw new BookingConflictException('Selected slot is no longer available', 'TIME_SLOT_TAKEN');
                }

                throw new BookingUnavailableException($this->availabilityMessage($reasonCode), $reasonCode);
            }

            $endTime = $this->appointmentService->calculateEndTime($time, $totalDuration);
            $clientData = $this->resolveClientData($data, $options, $isGuest);
            $initialStatus = $this->resolveInitialStatus($salon, $staff, $isManualBooking, $options);

            try {
                $appointment = Appointment::create([
                    'client_id' => $clientData['client_id'],
                    'client_name' => $clientData['client_name'],
                    'client_email' => $clientData['client_email'],
                    'client_phone' => $clientData['client_phone'],
                    'is_guest' => $clientData['is_guest'],
                    'guest_address' => $clientData['guest_address'],
                    'salon_id' => $salon->id,
                    'staff_id' => $staff->id,
                    'service_id' => count($serviceIds) === 1 ? $serviceIds[0] : null,
                    'service_ids' => $serviceIds,
                    'date' => $dateForDb,
                    'time' => $time,
                    'end_time' => $endTime,
                    'status' => $initialStatus,
                    'notes' => $this->buildNotes($data['notes'] ?? null, $options['notes_suffix'] ?? null),
                    'booking_source' => $bookingSource,
                    'idempotency_key' => $idempotencyKey,
                    'source' => $options['source'] ?? $data['source'] ?? $bookingSource,
                    'import_batch_id' => $options['import_batch_id'] ?? $data['import_batch_id'] ?? null,
                    'total_price' => $totalPrice,
                    'payment_status' => 'pending',
                ]);
            } catch (QueryException $e) {
                if ($idempotencyKey && $this->isIdempotencyException($e)) {
                    $existingAppointment = $this->findAppointmentByIdempotencyKey($salon->id, $bookingSource, $idempotencyKey);
                    if ($existingAppointment) {
                        return ['appointment' => $existingAppointment, 'created' => false];
                    }
                }

                if ($this->isBookingConflictException($e)) {
                    throw new BookingConflictException('Selected slot is no longer available', 'TIME_SLOT_TAKEN');
                }

                throw $e;
            }

            return ['appointment' => $appointment, 'created' => true];
        });

        $appointment = $result['appointment'];

        // Notifications/emails are dispatched only after the booking transaction
        // commits. This keeps row/advisory locks held for the shortest possible
        // time and avoids sending messages for a booking that could still roll
        // back. They remain best-effort: a failure here never voids the booking.
        if ($result['created'] === true) {
            if (($options['send_notifications'] ?? true) === true) {
                try {
                    $this->notificationService->sendNewAppointmentNotifications($appointment);
                } catch (\Throwable $e) {
                    Log::warning('Booking notification dispatch failed', [
                        'appointment_id' => $appointment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (($options['send_email'] ?? true) === true && !empty($appointment->client_email)) {
                try {
                    Mail::to($appointment->client_email)->send(new AppointmentConfirmationMail($appointment));
                } catch (\Throwable $e) {
                    Log::warning('Booking confirmation email failed', [
                        'appointment_id' => $appointment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $appointment->load(['salon', 'staff', 'service']);
    }

    /**
     * Update scheduling/status fields without allowing a hidden double booking.
     */
    public function updateAppointment(Appointment $appointment, array $data): Appointment
    {
        return DB::transaction(function () use ($appointment, $data) {
            $appointment = Appointment::lockForUpdate()->findOrFail($appointment->id);

            $newStatus = $data['status'] ?? $appointment->status;
            $touchesSchedule = array_key_exists('date', $data)
                || array_key_exists('time', $data)
                || array_key_exists('staff_id', $data)
                || array_key_exists('service_id', $data)
                || array_key_exists('services', $data)
                || array_key_exists('service_ids', $data);

            $activatesBlockingStatus = !in_array((string) $appointment->status, self::BLOCKING_STATUSES, true)
                && in_array((string) $newStatus, self::BLOCKING_STATUSES, true);

            $updateData = $data;

            if ($touchesSchedule || $activatesBlockingStatus) {
                $salonId = (int) ($data['salon_id'] ?? $appointment->salon_id);
                $staffId = (int) ($data['staff_id'] ?? $appointment->staff_id);
                $dateForDb = array_key_exists('date', $data)
                    ? $this->normalizeDate((string) $data['date'])
                    : $appointment->date->format('Y-m-d');
                $time = array_key_exists('time', $data)
                    ? $this->normalizeTime((string) $data['time'])
                    : substr((string) $appointment->time, 0, 5);
                $serviceIds = $this->resolveServiceIds($data, $appointment);

                $this->assertBookableDateTime($dateForDb, $time, false);

                $salon = Salon::lockForUpdate()->findOrFail($salonId);
                [$services, $totalDuration, $totalPrice] = $this->loadServicesForSalon($salon->id, $serviceIds);
                $staff = $this->loadStaffForBooking($salon->id, $staffId);
                $this->assertStaffCanBeBooked($staff, $serviceIds, false);

                if (in_array((string) $newStatus, self::BLOCKING_STATUSES, true)
                    && !$this->appointmentService->isStaffAvailable($staff, $dateForDb, $time, $totalDuration, (string) $appointment->id)) {
                    if ($this->hasBlockingOverlap($staff, $dateForDb, $time, $totalDuration, (string) $appointment->id)) {
                        throw new BookingConflictException('Selected slot is no longer available', 'TIME_SLOT_TAKEN');
                    }

                    $reasonCode = $this->appointmentService->getStaffUnavailabilityReason($staff, $dateForDb, $time, $totalDuration, (string) $appointment->id);
                    throw new BookingUnavailableException($this->availabilityMessage($reasonCode ?? 'UNAVAILABLE'), $reasonCode ?? 'UNAVAILABLE');
                }

                $updateData['salon_id'] = $salon->id;
                $updateData['staff_id'] = $staff->id;
                $updateData['date'] = $dateForDb;
                $updateData['time'] = $time;
                $updateData['end_time'] = $this->appointmentService->calculateEndTime($time, $totalDuration);
                $updateData['total_price'] = $totalPrice;
                $updateData['service_id'] = count($serviceIds) === 1 ? $serviceIds[0] : null;
                $updateData['service_ids'] = $serviceIds;
            }

            try {
                $appointment->update($updateData);
            } catch (QueryException $e) {
                if ($this->isBookingConflictException($e)) {
                    throw new BookingConflictException('Selected slot is no longer available', 'TIME_SLOT_TAKEN');
                }

                throw $e;
            }

            return $appointment->fresh(['salon', 'staff', 'service']);
        });
    }

    /**
     * Backward-compatible chatbot entrypoint.
     */
    public function createPublicBooking(array $data): Appointment
    {
        $clientName = trim((string) ($data['client_name'] ?? ''));
        $clientPhone = trim((string) ($data['client_phone'] ?? ''));
        if ($clientName === '' || $clientPhone === '') {
            throw new \InvalidArgumentException('client_name and client_phone are required');
        }

        return $this->createAppointment($data, [
            'booking_source' => 'chatbot',
            'is_guest' => true,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'notes_suffix' => 'Rezervacija preko Instagram/Facebook chatbota',
        ]);
    }

    /**
     * Resolve service IDs from payload or legacy appointment data.
     */
    public function resolveServiceIds(array $data, ?Appointment $appointment = null): array
    {
        if (!empty($data['services']) && is_array($data['services'])) {
            return array_values(array_unique(array_map(function ($service) {
                return (int) ($service['id'] ?? $service['serviceId'] ?? 0);
            }, $data['services'])));
        }

        if (!empty($data['service_ids']) && is_array($data['service_ids'])) {
            return array_values(array_unique(array_map('intval', $data['service_ids'])));
        }

        if (!empty($data['service_id'])) {
            return [(int) $data['service_id']];
        }

        if ($appointment) {
            if (!empty($appointment->service_ids) && is_array($appointment->service_ids)) {
                return array_values(array_unique(array_map('intval', $appointment->service_ids)));
            }

            if (!empty($appointment->service_id)) {
                return [(int) $appointment->service_id];
            }
        }

        return [];
    }

    public function normalizeDate(string $date): string
    {
        $date = trim($date);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $date)) {
            return Carbon::createFromFormat('d.m.Y', $date)->format('Y-m-d');
        }

        return Carbon::parse($date)->format('Y-m-d');
    }

    public function normalizeTime(string $time): string
    {
        $time = substr(trim($time), 0, 5);

        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw new \InvalidArgumentException('time is invalid');
        }

        return $time;
    }

    private function loadServicesForSalon(int $salonId, array $serviceIds): array
    {
        $serviceIds = array_values(array_filter(array_unique(array_map('intval', $serviceIds))));
        if (empty($serviceIds)) {
            throw new \InvalidArgumentException('service_id or services array is required');
        }

        $services = Service::where('salon_id', $salonId)
            ->whereIn('id', $serviceIds)
            ->get();

        if ($services->count() !== count($serviceIds)) {
            throw new \RuntimeException('One or more services are not valid for this salon');
        }

        $totalDuration = (int) $services->sum('duration');
        if ($totalDuration <= 0) {
            throw new \RuntimeException('Ne mozete rezervisati usluge koje nemaju trajanje.');
        }

        $totalPrice = (float) $services->sum(function ($service) {
            return (float) ($service->discount_price ?? $service->price ?? 0);
        });

        return [$services, $totalDuration, $totalPrice];
    }

    private function loadStaffForBooking(int $salonId, int $staffId): Staff
    {
        return Staff::where('id', $staffId)
            ->where('salon_id', $salonId)
            ->with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations', 'services'])
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertStaffCanBeBooked(Staff $staff, array $serviceIds, bool $enforceAcceptsBookings): void
    {
        if (!$staff->is_active) {
            throw new \RuntimeException('Selected staff is not active');
        }

        if ($enforceAcceptsBookings && $staff->accepts_bookings === false) {
            throw new \RuntimeException('Selected staff does not accept online bookings');
        }

        foreach ($serviceIds as $serviceId) {
            if (!$staff->services->contains('id', (int) $serviceId)) {
                throw new \RuntimeException('Selected staff cannot perform all requested services');
            }
        }
    }

    private function assertBookableDateTime(string $date, string $time, bool $allowPast): void
    {
        if ($allowPast) {
            return;
        }

        // date/time are naive local wall-clock; evaluate "is in the past"
        // entirely in the business timezone so both sides share one clock.
        $tz = config('app.business_timezone', 'Europe/Sarajevo');
        $startsAt = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}", $tz);
        if ($startsAt->lessThanOrEqualTo(Carbon::now($tz))) {
            throw new BookingUnavailableException('Termin mora biti u buducnosti.', 'PAST_TIME');
        }
    }

    private function resolveClientData(array $data, array $options, bool $isGuest): array
    {
        $clientId = $data['client_id'] ?? null;
        $clientName = trim((string) ($data['client_name'] ?? $data['guest_name'] ?? ''));
        $clientEmail = trim((string) ($data['client_email'] ?? $data['guest_email'] ?? ''));
        $clientPhone = trim((string) ($data['client_phone'] ?? $data['guest_phone'] ?? ''));

        if (!empty($options['client']) && $options['client'] instanceof User) {
            $client = $options['client'];
            $clientId = $client->id;
            $clientName = $client->name;
            $clientEmail = (string) $client->email;
            $clientPhone = (string) ($client->phone ?? '');
            $isGuest = false;
        } elseif ($isGuest && ($options['create_guest_user'] ?? true) && $clientEmail !== '') {
            $guestUser = $this->findOrCreateGuestUser([
                'name' => $clientName,
                'email' => $clientEmail,
                'phone' => $clientPhone,
            ]);
            $clientId = $guestUser?->id;
        }

        if ($clientName === '') {
            throw new \InvalidArgumentException('client_name or guest_name is required');
        }

        if ($isGuest && $clientPhone === '') {
            throw new \InvalidArgumentException('client_phone or guest_phone is required');
        }

        return [
            'client_id' => $clientId,
            'client_name' => $clientName,
            'client_email' => $clientEmail !== '' ? $clientEmail : null,
            'client_phone' => $clientPhone,
            'is_guest' => $isGuest,
            'guest_address' => $data['guest_address'] ?? $data['client_address'] ?? null,
        ];
    }

    private function resolveInitialStatus(Salon $salon, Staff $staff, bool $isManualBooking, array $options): string
    {
        if (!empty($options['initial_status'])) {
            return (string) $options['initial_status'];
        }

        if ($isManualBooking) {
            return 'confirmed';
        }

        return ($salon->auto_confirm || $staff->auto_confirm) ? 'confirmed' : 'pending';
    }

    private function buildNotes(?string $notes, ?string $suffix): ?string
    {
        $notes = trim((string) $notes);
        $suffix = trim((string) $suffix);

        if ($notes === '') {
            return $suffix !== '' ? $suffix : null;
        }

        return $suffix !== '' ? $notes . "\n" . $suffix : $notes;
    }

    private function findAvailableStaff(int $salonId, array $serviceIds, string $date, string $time, int $duration): ?int
    {
        $query = Staff::where('salon_id', $salonId)
            ->whereRaw('is_active = true')
            ->whereRaw('accepts_bookings = true')
            ->with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations', 'services'])
            ->orderBy('display_order')
            ->orderBy('id');

        foreach ($serviceIds as $serviceId) {
            $query->whereHas('services', function ($q) use ($serviceId) {
                $q->where('services.id', $serviceId);
            });
        }

        foreach ($query->get() as $staff) {
            if ($this->appointmentService->isStaffAvailable($staff, $date, $time, $duration)) {
                return (int) $staff->id;
            }
        }

        return null;
    }

    private function hasBlockingOverlap(Staff $staff, string $date, string $time, int $duration, ?string $excludeAppointmentId = null): bool
    {
        $appointmentStart = strtotime($time);
        $appointmentEnd = strtotime("+{$duration} minutes", $appointmentStart);

        $query = Appointment::where('staff_id', $staff->id)
            ->whereDate('date', $date)
            ->whereIn('status', self::BLOCKING_STATUSES);

        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        foreach ($query->get() as $appointment) {
            $interval = $this->appointmentService->resolveBlockingInterval($appointment);
            if ($interval === null) {
                continue;
            }

            [$existingStart, $existingEnd] = $interval;

            if ($appointmentStart < $existingEnd && $appointmentEnd > $existingStart) {
                return true;
            }
        }

        return false;
    }

    private function findOrCreateGuestUser(array $data): ?User
    {
        $email = $data['email'] ?? null;
        if (!$email) {
            return null;
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            $updates = [];

            if (!empty($data['name']) && strlen((string) $data['name']) > strlen((string) $user->name)) {
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

        Log::info('Creating guest user for booking', ['email' => $email]);

        // role is set explicitly (not mass assignable).
        $guest = new User([
            'name' => $data['name'] ?? 'Gost',
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'password' => bcrypt(Str::random(32)),
            'is_guest' => true,
            'created_via' => 'booking',
        ]);
        $guest->role = 'klijent';
        $guest->save();

        return $guest;
    }

    private function isBookingConflictException(QueryException $e): bool
    {
        return in_array($e->getCode(), ['23505', '23P01'], true)
            || str_contains($e->getMessage(), 'appointments_no_double_booking')
            || str_contains($e->getMessage(), 'appointments_no_overlap');
    }

    private function normalizeIdempotencyKey(mixed $key): ?string
    {
        $key = trim((string) $key);

        return $key === '' ? null : substr($key, 0, 100);
    }

    private function findAppointmentByIdempotencyKey(
        int $salonId,
        string $bookingSource,
        string $idempotencyKey,
        bool $lock = false
    ): ?Appointment {
        $query = Appointment::where('salon_id', $salonId)
            ->where('booking_source', $bookingSource)
            ->where('idempotency_key', $idempotencyKey);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->with(['salon', 'staff', 'service'])->first();
    }

    private function isIdempotencyException(QueryException $e): bool
    {
        return str_contains($e->getMessage(), 'appointments_idempotency_unique');
    }

    private function availabilityMessage(string $reasonCode): string
    {
        return match ($reasonCode) {
            'PAST_TIME' => 'Termin mora biti u buducnosti.',
            'STAFF_NOT_WORKING' => 'Odabrani frizer ne radi u tom terminu.',
            'OUTSIDE_WORKING_HOURS' => 'Termin je van radnog vremena.',
            'SALON_BREAK' => 'Salon ima pauzu u tom terminu.',
            'SALON_VACATION' => 'Salon je na odmoru u tom periodu.',
            'STAFF_BREAK' => 'Odabrani frizer ima pauzu u tom terminu.',
            'STAFF_VACATION' => 'Odabrani frizer je na odmoru u tom periodu.',
            'TIME_SLOT_TAKEN' => 'Ovaj termin je upravo zauzet.',
            default => 'Odabrani termin nije dostupan.',
        };
    }
}
