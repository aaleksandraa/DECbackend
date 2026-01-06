<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Notification;
use App\Models\Review;
use App\Models\Salon;
use App\Models\User;
use App\Mail\AppointmentCancelledMail;
use App\Mail\NewAppointmentNotificationMail;
use App\Mail\ReviewRequestMail;
use App\Events\NewNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Format date for display in European format (DD.MM.YYYY)
     */
    private function formatDate($date): string
    {
        if ($date instanceof Carbon) {
            return $date->format('d.m.Y');
        }
        return Carbon::parse($date)->format('d.m.Y');
    }

    /**
     * Format time for display (HH:MM)
     */
    private function formatTime(string $time): string
    {
        return Carbon::parse($time)->format('H:i');
    }

    /**
     * Send notifications for a new appointment.
     */
    public function sendNewAppointmentNotifications(Appointment $appointment): void
    {
        // Load relationships
        $appointment->load(['salon', 'staff', 'service', 'client']);

        $formattedDate = $this->formatDate($appointment->date);
        $formattedTime = $this->formatTime($appointment->time);
        $salon = $appointment->salon;
        $client = $appointment->client;
        $staff = $appointment->staff;

        // Get client name - use registered client name or guest name from appointment
        $clientName = $client ? $client->name : ($appointment->client_name ?? 'Gost');
        $isGuest = $appointment->is_guest || !$client;

        // Build service list
        $services = $appointment->services();
        if ($services->count() > 1) {
            $serviceNames = $services->pluck('name')->toArray();
            $serviceList = count($serviceNames) > 1
                ? implode(', ', array_slice($serviceNames, 0, -1)) . ' i ' . end($serviceNames)
                : $serviceNames[0];
        } else {
            $serviceList = $appointment->service ? $appointment->service->name : 'Usluga';
        }

        // Notify salon owner
        $owner = $salon->owner;
        $guestLabel = $isGuest ? ' (ručno dodano)' : '';
        $notification = Notification::create([
            'type' => 'new_appointment',
            'title' => 'Novi termin',
            'message' => "{$clientName}{$guestLabel} je zakazao/la termin za " .
                ($services->count() > 1 ? "usluge: {$serviceList}" : "uslugu '{$serviceList}'") .
                " kod {$staff->name} za {$formattedDate} u {$formattedTime}h",
            'recipient_id' => $owner->id,
            'related_id' => $appointment->id,
            'data' => [
                'appointment_date' => $formattedDate,
                'appointment_time' => $formattedTime,
            ],
        ]);

        // Broadcast instant notification
        broadcast(new NewNotification($notification, $owner->id));

        // Send email to salon owner
        if ($owner->email && $salon->email_notifications_enabled) {
            try {
                Mail::to($owner->email)->send(new NewAppointmentNotificationMail($appointment, 'salon'));
            } catch (\Exception $e) {
                Log::warning('Failed to send new appointment email to salon owner: ' . $e->getMessage());
            }
        }

        // Notify staff member
        if ($staff->user_id) {
            $staffNotification = Notification::create([
                'type' => 'new_appointment',
                'title' => 'Novi termin',
                'message' => "{$clientName}{$guestLabel} je zakazao/la termin za " .
                    ($services->count() > 1 ? "usluge: {$serviceList}" : "uslugu '{$serviceList}'") .
                    " za {$formattedDate} u {$formattedTime}h",
                'recipient_id' => $staff->user_id,
                'related_id' => $appointment->id,
                'data' => [
                    'appointment_date' => $formattedDate,
                    'appointment_time' => $formattedTime,
                ],
            ]);

            // Broadcast instant notification
            broadcast(new NewNotification($staffNotification, $staff->user_id));

            // Send email to staff member
            $staffUser = User::find($staff->user_id);
            if ($staffUser && $staffUser->email) {
                try {
                    Mail::to($staffUser->email)->send(new NewAppointmentNotificationMail($appointment, 'staff'));
                } catch (\Exception $e) {
                    Log::warning('Failed to send new appointment email to staff: ' . $e->getMessage());
                }
            }
        }

        // Only notify client if they are a registered user (not a guest)
        if ($client && $appointment->client_id) {
            $statusMessage = $appointment->status === 'confirmed'
                ? 'je automatski potvrđen'
                : 'čeka potvrdu';

            $clientNotification = Notification::create([
                'type' => $appointment->status === 'confirmed' ? 'appointment_confirmed' : 'new_appointment',
                'title' => $appointment->status === 'confirmed' ? 'Termin potvrđen' : 'Termin zakazan',
                'message' => "Vaš termin za " .
                    ($services->count() > 1 ? "usluge: {$serviceList}" : "'{$serviceList}'") .
                    " kod {$staff->name} u salonu '{$salon->name}' za {$formattedDate} u {$formattedTime}h {$statusMessage}",
                'recipient_id' => $appointment->client_id,
                'related_id' => $appointment->id,
            ]);

            // Broadcast instant notification
            broadcast(new NewNotification($clientNotification, $appointment->client_id));
        }
    }

    /**
     * Send grouped notifications for multiple appointments (multi-service booking).
     * This sends ONE notification with all services listed instead of separate notifications.
     */
    public function sendMultiServiceAppointmentNotifications(array $appointments): void
    {
        if (empty($appointments)) {
            return;
        }

        // Load relationships for all appointments
        foreach ($appointments as $appointment) {
            $appointment->load(['salon', 'staff', 'service', 'client']);
        }

        // Use first appointment for common data
        $firstAppointment = $appointments[0];
        $formattedDate = $this->formatDate($firstAppointment->date);
        $formattedTime = $this->formatTime($firstAppointment->time);
        $salon = $firstAppointment->salon;
        $client = $firstAppointment->client;
        $staff = $firstAppointment->staff;

        // Get client name
        $clientName = $client ? $client->name : ($firstAppointment->client_name ?? 'Gost');
        $isGuest = $firstAppointment->is_guest || !$client;

        // Build service list
        $serviceNames = array_map(fn($apt) => $apt->service->name, $appointments);
        $serviceList = count($serviceNames) > 1
            ? implode(', ', array_slice($serviceNames, 0, -1)) . ' i ' . end($serviceNames)
            : $serviceNames[0];

        // Notify salon owner
        $owner = $salon->owner;
        $guestLabel = $isGuest ? ' (ručno dodano)' : '';
        $ownerNotification = Notification::create([
            'type' => 'new_appointment',
            'title' => 'Novi termin',
            'message' => "{$clientName}{$guestLabel} je zakazao/la termin za usluge: {$serviceList} kod {$staff->name} za {$formattedDate} u {$formattedTime}h",
            'recipient_id' => $owner->id,
            'related_id' => $firstAppointment->id,
        ]);

        // Broadcast instant notification
        broadcast(new NewNotification($ownerNotification, $owner->id));

        // Send email to salon owner (with all appointments)
        if ($owner->email && $salon->email_notifications_enabled) {
            try {
                Mail::to($owner->email)->send(new NewAppointmentNotificationMail($firstAppointment, 'salon', $appointments));
            } catch (\Exception $e) {
                Log::warning('Failed to send multi-service appointment email to salon owner: ' . $e->getMessage());
            }
        }

        // Notify staff member
        if ($staff->user_id) {
            $staffNotification = Notification::create([
                'type' => 'new_appointment',
                'title' => 'Novi termin',
                'message' => "{$clientName}{$guestLabel} je zakazao/la termin za usluge: {$serviceList} za {$formattedDate} u {$formattedTime}h",
                'recipient_id' => $staff->user_id,
                'related_id' => $firstAppointment->id,
            ]);

            // Broadcast instant notification
            broadcast(new NewNotification($staffNotification, $staff->user_id));

            // Send email to staff member (with all appointments)
            $staffUser = User::find($staff->user_id);
            if ($staffUser && $staffUser->email) {
                try {
                    Mail::to($staffUser->email)->send(new NewAppointmentNotificationMail($firstAppointment, 'staff', $appointments));
                } catch (\Exception $e) {
                    Log::warning('Failed to send multi-service appointment email to staff: ' . $e->getMessage());
                }
            }
        }

        // Only notify client if they are a registered user (not a guest)
        if ($client && $firstAppointment->client_id) {
            $statusMessage = $firstAppointment->status === 'confirmed'
                ? 'je automatski potvrđen'
                : 'čeka potvrdu';

            $clientNotification = Notification::create([
                'type' => $firstAppointment->status === 'confirmed' ? 'appointment_confirmed' : 'new_appointment',
                'title' => $firstAppointment->status === 'confirmed' ? 'Termin potvrđen' : 'Termin zakazan',
                'message' => "Vaš termin za usluge: {$serviceList} kod {$staff->name} u salonu '{$salon->name}' za {$formattedDate} u {$formattedTime}h {$statusMessage}",
                'recipient_id' => $firstAppointment->client_id,
                'related_id' => $firstAppointment->id,
            ]);

            // Broadcast instant notification
            broadcast(new NewNotification($clientNotification, $firstAppointment->client_id));
        }
    }

    /**
     * Send notifications for appointment status change.
     */
    public function sendAppointmentStatusChangeNotifications(Appointment $appointment, string $oldStatus): void
    {
        // Load relationships
        $appointment->load(['salon', 'staff', 'service', 'client']);

        $formattedDate = $this->formatDate($appointment->date);
        $formattedTime = $this->formatTime($appointment->time);
        $salon = $appointment->salon;
        $service = $appointment->service;
        $staff = $appointment->staff;
        $client = $appointment->client;

        // Get client name - use registered client name or guest name from appointment
        $clientName = $client ? $client->name : ($appointment->client_name ?? 'Gost');

        // Only notify client if they are a registered user (not a guest)
        if ($client && $appointment->client_id) {
            switch ($appointment->status) {
                case 'confirmed':
                    $confirmedNotification = Notification::create([
                        'type' => 'appointment_confirmed',
                        'title' => 'Termin potvrđen',
                        'message' => "Vaš termin za '{$service->name}' u salonu '{$salon->name}' za {$formattedDate} u {$formattedTime}h je potvrđen",
                        'recipient_id' => $appointment->client_id,
                        'related_id' => $appointment->id,
                    ]);
                    broadcast(new NewNotification($confirmedNotification, $appointment->client_id));
                    break;

                case 'cancelled':
                    $cancelledNotification = Notification::create([
                        'type' => 'appointment_cancelled',
                        'title' => 'Termin otkazan',
                        'message' => "Vaš termin za '{$service->name}' u salonu '{$salon->name}' za {$formattedDate} u {$formattedTime}h je otkazan",
                        'recipient_id' => $appointment->client_id,
                        'related_id' => $appointment->id,
                    ]);
                    broadcast(new NewNotification($cancelledNotification, $appointment->client_id));

                    // Send email to client when salon cancels their appointment
                    $client = $appointment->client;
                    if ($client && $client->email) {
                        Mail::to($client->email)->send(new AppointmentCancelledMail($appointment, 'client', 'salon'));
                    }
                    break;

                case 'completed':
                    $completedNotification = Notification::create([
                        'type' => 'appointment_completed',
                        'title' => 'Termin završen',
                        'message' => "Vaš termin za '{$service->name}' kod {$staff->name} u salonu '{$salon->name}' je uspješno završen. Ostavite recenziju!",
                        'recipient_id' => $appointment->client_id,
                        'related_id' => $appointment->id,
                        'data' => [
                            'salon_id' => $salon->id,
                            'salon_slug' => $salon->slug,
                            'salon_name' => $salon->name,
                            'service_name' => $service->name,
                            'staff_name' => $staff->name,
                            'can_review' => true,
                        ],
                    ]);
                    broadcast(new NewNotification($completedNotification, $appointment->client_id));

                    // Send email to client with review request
                    $this->sendReviewRequestEmail($appointment);
                    break;
            }
        }

        // Notify salon owner if client cancelled
        if ($appointment->status === 'cancelled' && $oldStatus !== 'cancelled') {
            $owner = $salon->owner;

            $ownerCancelNotification = Notification::create([
                'type' => 'appointment_cancelled',
                'title' => 'Termin otkazan',
                'message' => "{$clientName} je otkazao/la termin za '{$service->name}' kod {$staff->name} za {$formattedDate} u {$formattedTime}h",
                'recipient_id' => $owner->id,
                'related_id' => $appointment->id,
            ]);
            broadcast(new NewNotification($ownerCancelNotification, $owner->id));

            // Also notify staff member
            if ($staff->user_id) {
                $staffCancelNotification = Notification::create([
                    'type' => 'appointment_cancelled',
                    'title' => 'Termin otkazan',
                    'message' => "{$clientName} je otkazao/la termin za '{$service->name}' za {$formattedDate} u {$formattedTime}h",
                    'recipient_id' => $staff->user_id,
                    'related_id' => $appointment->id,
                ]);
                broadcast(new NewNotification($staffCancelNotification, $staff->user_id));
            }

            // Send cancellation email to salon owner
            if ($owner->email) {
                Mail::to($owner->email)->send(new AppointmentCancelledMail($appointment, 'salon', 'client'));
            }
        }

        // Notify staff member about confirmation
        if ($staff->user_id && $appointment->status === 'confirmed' && $oldStatus !== 'confirmed') {
            $staffConfirmNotification = Notification::create([
                'type' => 'appointment_confirmed',
                'title' => 'Termin potvrđen',
                'message' => "Termin za '{$service->name}' sa klijentom {$clientName} za {$formattedDate} u {$formattedTime}h je potvrđen",
                'recipient_id' => $staff->user_id,
                'related_id' => $appointment->id,
            ]);
            broadcast(new NewNotification($staffConfirmNotification, $staff->user_id));
        }
    }

    /**
     * Send notifications for a new review.
     */
    public function sendNewReviewNotifications(Review $review): void
    {
        // Load relationships
        $review->load(['salon', 'staff', 'client']);

        $salon = $review->salon;
        $owner = $salon->owner;
        $client = $review->client;
        $stars = str_repeat('★', $review->rating) . str_repeat('☆', 5 - $review->rating);

        // Notify salon owner
        $ownerReviewNotification = Notification::create([
            'type' => 'new_review',
            'title' => 'Nova recenzija',
            'message' => "{$client->name} je ostavio/la recenziju {$stars} ({$review->rating}/5) za vaš salon",
            'recipient_id' => $owner->id,
            'related_id' => $review->id,
        ]);
        broadcast(new NewNotification($ownerReviewNotification, $owner->id));

        // Notify staff member
        if ($review->staff_id) {
            $staff = $review->staff;
            if ($staff->user_id) {
                $staffReviewNotification = Notification::create([
                    'type' => 'new_review',
                    'title' => 'Nova recenzija',
                    'message' => "{$client->name} je ostavio/la recenziju {$stars} ({$review->rating}/5) za vas",
                    'recipient_id' => $staff->user_id,
                    'related_id' => $review->id,
                ]);
                broadcast(new NewNotification($staffReviewNotification, $staff->user_id));
            }
        }
    }

    /**
     * Send notification for a review response.
     */
    public function sendReviewResponseNotification(Review $review): void
    {
        $review->load(['salon', 'client']);

        $responseText = $review->response['text'] ?? 'Pogledajte odgovor';
        $shortResponse = strlen($responseText) > 50 ? substr($responseText, 0, 50) . '...' : $responseText;

        $responseNotification = Notification::create([
            'type' => 'review_response',
            'title' => 'Odgovor na recenziju',
            'message' => "Salon '{$review->salon->name}' je odgovorio na vašu recenziju: \"{$shortResponse}\"",
            'recipient_id' => $review->client_id,
            'related_id' => $review->id,
        ]);
        broadcast(new NewNotification($responseNotification, $review->client_id));
    }

    /**
     * Send notification for adding a salon to favorites.
     */
    public function sendFavoriteAddedNotification(User $user, Salon $salon): void
    {
        $userFavoriteNotification = Notification::create([
            'type' => 'favorite_added',
            'title' => 'Salon dodan u omiljene',
            'message' => "Dodali ste '{$salon->name}' u omiljene salone. Pratite specijalne ponude!",
            'recipient_id' => $user->id,
            'related_id' => $salon->id,
        ]);
        broadcast(new NewNotification($userFavoriteNotification, $user->id));

        // Also notify salon owner
        $ownerFavoriteNotification = Notification::create([
            'type' => 'new_favorite',
            'title' => 'Novi pratilac',
            'message' => "{$user->name} je dodao/la vaš salon u omiljene",
            'recipient_id' => $salon->owner_id,
            'related_id' => $salon->id,
        ]);
        broadcast(new NewNotification($ownerFavoriteNotification, $salon->owner_id));
    }

    /**
     * Send notification for removing a salon from favorites.
     */
    public function sendFavoriteRemovedNotification(User $user, Salon $salon): void
    {
        // Optional: We might not want to notify about removals
        // Notification::create([
        //     'type' => 'favorite_removed',
        //     'title' => 'Salon uklonjen iz omiljenih',
        //     'message' => "Salon '{$salon->name}' je uklonjen iz vaših omiljenih",
        //     'recipient_id' => $user->id,
        //     'related_id' => $salon->id,
        // ]);
    }

    /**
     * Send notification for salon status change.
     */
    public function sendSalonStatusChangeNotification(Salon $salon, string $status): void
    {
        $statusText = match($status) {
            'approved' => 'odobren i sada je vidljiv klijentima',
            'suspended' => 'suspendovan i više nije vidljiv klijentima',
            'pending' => 'stavljen na čekanje za pregled',
            default => $status
        };

        $statusNotification = Notification::create([
            'type' => 'salon_status_change',
            'title' => 'Status salona promijenjen',
            'message' => "Vaš salon '{$salon->name}' je {$statusText}",
            'recipient_id' => $salon->owner_id,
            'related_id' => $salon->id,
        ]);
        broadcast(new NewNotification($statusNotification, $salon->owner_id));
    }

    /**
     * Send notification for password reset.
     */
    public function sendPasswordResetNotification(User $user, string $newPassword): void
    {
        $passwordNotification = Notification::create([
            'type' => 'password_reset',
            'title' => 'Lozinka resetovana',
            'message' => "Administrator je resetovao vašu lozinku. Nova privremena lozinka: {$newPassword}. Molimo promijenite je nakon prijave.",
            'recipient_id' => $user->id,
        ]);
        broadcast(new NewNotification($passwordNotification, $user->id));
    }

    /**
     * Send notification for admin message.
     */
    public function sendAdminMessageNotification(User $user, string $subject, string $message): void
    {
        $adminNotification = Notification::create([
            'type' => 'admin_message',
            'title' => $subject,
            'message' => $message,
            'recipient_id' => $user->id,
        ]);
        broadcast(new NewNotification($adminNotification, $user->id));
    }

    /**
     * Send reminder notification for upcoming appointment.
     * For registered users: creates in-app notification
     * For guest bookings: only sends email (no in-app notification)
     */
    public function sendAppointmentReminderNotification(Appointment $appointment): void
    {
        $appointment->load(['salon', 'staff', 'service', 'client']);

        $formattedDate = $this->formatDate($appointment->date);
        $formattedTime = $this->formatTime($appointment->time);
        $salon = $appointment->salon;
        $service = $appointment->service;
        $staff = $appointment->staff;

        // Only create in-app notification for registered users
        if ($appointment->client_id && $appointment->client) {
            $reminderNotification = Notification::create([
                'type' => 'appointment_reminder',
                'title' => 'Podsjetnik za termin',
                'message' => "Podsjetnik: Imate zakazan termin za '{$service->name}' kod {$staff->name} u salonu '{$salon->name}' za {$formattedDate} u {$formattedTime}h",
                'recipient_id' => $appointment->client_id,
                'related_id' => $appointment->id,
            ]);
            broadcast(new NewNotification($reminderNotification, $appointment->client_id));

            Log::info("In-app reminder notification created for registered client", [
                'appointment_id' => $appointment->id,
                'client_id' => $appointment->client_id,
            ]);
        } else {
            Log::info("Skipping in-app notification for guest booking", [
                'appointment_id' => $appointment->id,
                'has_email' => !empty($appointment->client_email),
            ]);
        }
    }

    /**
     * Send review request email to client after appointment is completed.
     */
    public function sendReviewRequestEmail(Appointment $appointment): void
    {
        try {
            $appointment->load(['salon', 'staff', 'service', 'client']);

            // Only send email if client is a registered user with email
            $client = $appointment->client;
            if (!$client || !$client->email) {
                Log::info("Skipping review request email - no registered client for appointment {$appointment->id}");
                return;
            }

            Mail::to($client->email)->send(new ReviewRequestMail($appointment));

            Log::info("Review request email sent to {$client->email} for appointment {$appointment->id}");
        } catch (\Exception $e) {
            Log::error("Failed to send review request email for appointment {$appointment->id}: " . $e->getMessage());
        }
    }
}
