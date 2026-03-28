<?php

namespace App\Services;

use App\Mail\AppointmentConfirmationMail;
use App\Models\Appointment;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BookingService
{
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
     * Create booking from chatbot conversation context.
     */
    public function createPublicBooking(array $data): Appointment
    {
        $salonId = (int) ($data['salon_id'] ?? 0);
        if ($salonId <= 0) {
            throw new \InvalidArgumentException('salon_id is required');
        }

        $serviceIds = $this->resolveServiceIds($data);
        if (empty($serviceIds)) {
            throw new \InvalidArgumentException('service_ids are required');
        }

        $clientName = trim((string) ($data['client_name'] ?? ''));
        $clientPhone = trim((string) ($data['client_phone'] ?? ''));
        if ($clientName === '' || $clientPhone === '') {
            throw new \InvalidArgumentException('client_name and client_phone are required');
        }

        $dateForDb = $this->normalizeDate((string) ($data['date'] ?? ''));
        $time = substr(trim((string) ($data['time'] ?? '')), 0, 5);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateForDb)) {
            throw new \InvalidArgumentException('date is invalid');
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw new \InvalidArgumentException('time is invalid');
        }

        return DB::transaction(function () use ($data, $salonId, $serviceIds, $clientName, $clientPhone, $dateForDb, $time) {
            $salon = Salon::lockForUpdate()->findOrFail($salonId);

            $services = Service::where('salon_id', $salon->id)
                ->whereIn('id', $serviceIds)
                ->get();

            if ($services->count() !== count($serviceIds)) {
                throw new \RuntimeException('One or more services are not valid for this salon');
            }

            $totalDuration = max(1, (int) $services->sum('duration'));
            $totalPrice = (float) $services->sum(function ($service) {
                return (float) ($service->discount_price ?? $service->price ?? 0);
            });

            $staffId = isset($data['staff_id'])
                ? (int) $data['staff_id']
                : (int) $this->findAvailableStaff($salon->id, $serviceIds, $dateForDb, $time, $totalDuration);

            if ($staffId <= 0) {
                throw new \RuntimeException('No available staff member for the selected slot');
            }

            $staff = Staff::where('id', $staffId)
                ->where('salon_id', $salon->id)
                ->with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations', 'services'])
                ->lockForUpdate()
                ->firstOrFail();

            foreach ($serviceIds as $serviceId) {
                if (!$staff->services->contains('id', $serviceId)) {
                    throw new \RuntimeException('Selected staff cannot perform all requested services');
                }
            }

            if (!$this->appointmentService->isStaffAvailable($staff, $dateForDb, $time, $totalDuration)) {
                throw new \RuntimeException('Selected slot is no longer available');
            }

            $endTime = $this->appointmentService->calculateEndTime($time, $totalDuration);

            $clientEmail = trim((string) ($data['client_email'] ?? ''));
            $guestUser = $this->findOrCreateGuestUser([
                'name' => $clientName,
                'email' => $clientEmail !== '' ? $clientEmail : null,
                'phone' => $clientPhone,
            ]);

            $initialStatus = ($salon->auto_confirm || $staff->auto_confirm) ? 'confirmed' : 'pending';

            $notes = trim((string) ($data['notes'] ?? ''));
            $notes = $notes !== '' ? $notes . "\nRezervacija preko Instagram/Facebook chatbota" : 'Rezervacija preko Instagram/Facebook chatbota';

            $appointment = Appointment::create([
                'client_id' => $guestUser?->id,
                'client_name' => $clientName,
                'client_email' => $clientEmail !== '' ? $clientEmail : null,
                'client_phone' => $clientPhone,
                'is_guest' => true,
                'guest_address' => $data['guest_address'] ?? null,
                'salon_id' => $salon->id,
                'staff_id' => $staff->id,
                'service_id' => count($serviceIds) === 1 ? $serviceIds[0] : null,
                'service_ids' => $serviceIds,
                'date' => $dateForDb,
                'time' => $time,
                'end_time' => $endTime,
                'status' => $initialStatus,
                'notes' => $notes,
                'booking_source' => 'chatbot',
                'total_price' => $totalPrice,
                'payment_status' => 'pending',
            ]);

            $this->notificationService->sendNewAppointmentNotifications($appointment);

            if (!empty($appointment->client_email)) {
                Mail::to($appointment->client_email)->send(new AppointmentConfirmationMail($appointment));
            }

            return $appointment;
        });
    }

    /**
     * Resolve service IDs from payload.
     */
    private function resolveServiceIds(array $data): array
    {
        if (!empty($data['service_ids']) && is_array($data['service_ids'])) {
            return array_values(array_unique(array_map('intval', $data['service_ids'])));
        }

        if (!empty($data['service_id'])) {
            return [(int) $data['service_id']];
        }

        return [];
    }

    /**
     * Find first available staff member who can perform all requested services.
     */
    private function findAvailableStaff(int $salonId, array $serviceIds, string $date, string $time, int $duration): ?int
    {
        $query = Staff::where('salon_id', $salonId)
            ->whereRaw('is_active = true')
            ->with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations', 'services'])
            ->orderBy('display_order')
            ->orderBy('id');

        foreach ($serviceIds as $serviceId) {
            $query->whereHas('services', function ($q) use ($serviceId) {
                $q->where('services.id', $serviceId);
            });
        }

        $staffMembers = $query->get();

        foreach ($staffMembers as $staff) {
            if ($this->appointmentService->isStaffAvailable($staff, $date, $time, $duration)) {
                return (int) $staff->id;
            }
        }

        return null;
    }

    /**
     * Normalize date to YYYY-MM-DD format.
     */
    private function normalizeDate(string $date): string
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

    /**
     * Find existing user by email or create guest user.
     */
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

        Log::info('Creating guest user for chatbot booking', ['email' => $email]);

        return User::create([
            'name' => $data['name'] ?? 'Gost',
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'password' => bcrypt(str()->random(32)),
            'email_verified_at' => null,
            'role' => 'klijent',
            'is_guest' => true,
            'created_via' => 'booking',
        ]);
    }
}

