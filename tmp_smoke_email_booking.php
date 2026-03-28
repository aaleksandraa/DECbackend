<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\WidgetController;
use App\Mail\AppointmentConfirmationMail;
use App\Models\Appointment;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Models\WidgetSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

function decodeCalendarUrl(string $url): array
{
    $parts = parse_url($url);
    $query = [];

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    $decoded = [];
    foreach ($query as $key => $value) {
        if (is_string($value)) {
            $decoded[$key] = urldecode($value);
        } else {
            $decoded[$key] = $value;
        }
    }

    return [
        'host' => $parts['host'] ?? null,
        'path' => $parts['path'] ?? null,
        'query' => $decoded,
    ];
}

function extractIcsSnapshot(string $ics): array
{
    $wantedPrefixes = [
        'X-WR-TIMEZONE:',
        'DTSTART',
        'DTEND',
        'SUMMARY:',
        'DESCRIPTION:',
        'LOCATION:',
    ];

    $lines = preg_split('/\r\n|\n|\r/', $ics) ?: [];
    $result = [];

    foreach ($lines as $line) {
        foreach ($wantedPrefixes as $prefix) {
            if (str_starts_with($line, $prefix)) {
                $result[] = $line;
                break;
            }
        }
    }

    return $result;
}

function buildEmailSnapshot(Appointment $appointment): array
{
    $appointment = $appointment->fresh()->load(['salon', 'staff', 'service']);
    $mail = new AppointmentConfirmationMail($appointment);

    $googleDecoded = decodeCalendarUrl($mail->googleCalendarUrl);
    $outlookDecoded = decodeCalendarUrl($mail->outlookCalendarUrl);

    return [
        'appointment' => [
            'id' => $appointment->id,
            'booking_source' => $appointment->booking_source,
            'date_db' => $appointment->date ? $appointment->date->format('Y-m-d') : null,
            'time_db' => $appointment->time,
            'end_time_db' => $appointment->end_time,
            'status' => $appointment->status,
            'service' => $appointment->service?->name,
            'staff' => $appointment->staff?->name,
            'salon' => $appointment->salon?->name,
        ],
        'email_text_exact' => [
            'datum' => $mail->formattedDate,
            'vrijeme' => $mail->formattedTime . ' - ' . $mail->endTime . ' (' . $mail->totalDuration . ' min)',
        ],
        'calendar_links_exact' => [
            'google' => $mail->googleCalendarUrl,
            'outlook' => $mail->outlookCalendarUrl,
        ],
        'calendar_links_decoded' => [
            'google' => [
                'text' => $googleDecoded['query']['text'] ?? null,
                'dates' => $googleDecoded['query']['dates'] ?? null,
                'ctz' => $googleDecoded['query']['ctz'] ?? null,
                'location' => $googleDecoded['query']['location'] ?? null,
                'details' => $googleDecoded['query']['details'] ?? null,
            ],
            'outlook' => [
                'subject' => $outlookDecoded['query']['subject'] ?? null,
                'startdt' => $outlookDecoded['query']['startdt'] ?? null,
                'enddt' => $outlookDecoded['query']['enddt'] ?? null,
                'location' => $outlookDecoded['query']['location'] ?? null,
                'body' => $outlookDecoded['query']['body'] ?? null,
            ],
        ],
        'ics_exact_lines' => extractIcsSnapshot($mail->icsContent),
    ];
}

Mail::fake();

DB::beginTransaction();

try {
    $token = strtolower(Str::random(6));
    $appointmentDate = Carbon::now('Europe/Sarajevo')->next(Carbon::MONDAY);
    $dateEuro = $appointmentDate->format('d.m.Y');

    $owner = User::factory()->create([
        'name' => 'Smoke Owner ' . $token,
        'email' => 'smoke-owner-' . $token . '@example.com',
        'role' => 'salon',
    ]);

    $staffUser = User::factory()->create([
        'name' => 'Smoke Staff User ' . $token,
        'email' => 'smoke-staff-' . $token . '@example.com',
    ]);

    $salon = Salon::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Smoke Salon ' . $token,
        'slug' => 'smoke-salon-' . $token,
        'email' => 'smoke-salon-' . $token . '@example.com',
        'status' => 'approved',
        'working_hours' => [
            'monday' => ['start' => '08:00', 'end' => '20:00', 'is_open' => true],
            'tuesday' => ['start' => '08:00', 'end' => '20:00', 'is_open' => true],
            'wednesday' => ['start' => '08:00', 'end' => '20:00', 'is_open' => true],
            'thursday' => ['start' => '08:00', 'end' => '20:00', 'is_open' => true],
            'friday' => ['start' => '08:00', 'end' => '20:00', 'is_open' => true],
            'saturday' => ['start' => '08:00', 'end' => '18:00', 'is_open' => true],
            'sunday' => ['start' => '00:00', 'end' => '00:00', 'is_open' => false],
        ],
    ]);

    $staff = Staff::factory()->create([
        'salon_id' => $salon->id,
        'user_id' => $staffUser->id,
        'name' => 'Smoke Staff ' . $token,
        'working_hours' => [
            'monday' => ['start' => '08:00', 'end' => '20:00', 'is_working' => true],
            'tuesday' => ['start' => '08:00', 'end' => '20:00', 'is_working' => true],
            'wednesday' => ['start' => '08:00', 'end' => '20:00', 'is_working' => true],
            'thursday' => ['start' => '08:00', 'end' => '20:00', 'is_working' => true],
            'friday' => ['start' => '08:00', 'end' => '20:00', 'is_working' => true],
            'saturday' => ['start' => '08:00', 'end' => '18:00', 'is_working' => true],
            'sunday' => ['start' => '00:00', 'end' => '00:00', 'is_working' => false],
        ],
    ]);

    $service = Service::factory()->create([
        'salon_id' => $salon->id,
        'name' => 'Smoke Test Sisanje ' . $token,
        'duration' => 60,
        'price' => 35.00,
        'discount_price' => null,
        'category' => 'haircut',
        'is_active' => true,
    ]);

    $staff->services()->syncWithoutDetaching([$service->id]);

    $widget = WidgetSetting::create([
        'salon_id' => $salon->id,
        'api_key' => 'smoke_' . Str::random(56),
        'is_active' => true,
        'allowed_domains' => [],
        'theme' => [],
        'settings' => [],
    ]);

    $publicPayload = [
        'salon_id' => $salon->id,
        'staff_id' => $staff->id,
        'service_id' => $service->id,
        'date' => $dateEuro,
        'time' => '13:00',
        'guest_name' => 'Smoke Web Klijent ' . $token,
        'guest_email' => 'smoke-web-' . $token . '@example.com',
        'guest_phone' => '061234567',
        'guest_address' => 'Test adresa 1',
        'notes' => 'Smoke WEB test',
    ];

    $publicRequest = Request::create('/api/v1/public/book', 'POST', $publicPayload);
    $publicRequest->headers->set('Accept', 'application/json');
    $publicResponse = app(PublicController::class)->storeGuestAppointment($publicRequest);

    $publicStatus = $publicResponse->getStatusCode();
    $publicData = json_decode($publicResponse->getContent(), true);

    if ($publicStatus !== 201) {
        throw new RuntimeException('WEB booking failed: HTTP ' . $publicStatus . ' | ' . json_encode($publicData));
    }

    $webAppointmentId = $publicData['appointment']['id'] ?? null;
    if (!$webAppointmentId) {
        throw new RuntimeException('WEB booking response missing appointment id.');
    }

    $widgetPayload = [
        'api_key' => $widget->api_key,
        'salon_id' => $salon->id,
        'staff_id' => $staff->id,
        'service_id' => $service->id,
        'date' => $dateEuro,
        'time' => '15:00',
        'guest_name' => 'Smoke Widget Klijent ' . $token,
        'guest_email' => 'smoke-widget-' . $token . '@example.com',
        'guest_phone' => '062234567',
        'guest_address' => 'Test adresa 2',
        'notes' => 'Smoke WIDGET test',
    ];

    $widgetRequest = Request::create('/api/v1/widget/book', 'POST', $widgetPayload);
    $widgetRequest->headers->set('Accept', 'application/json');
    $widgetRequest->headers->set('referer', 'https://smoke.local');
    $widgetResponse = app(WidgetController::class)->book($widgetRequest);

    $widgetStatus = $widgetResponse->getStatusCode();
    $widgetData = json_decode($widgetResponse->getContent(), true);

    if ($widgetStatus !== 201) {
        throw new RuntimeException('WIDGET booking failed: HTTP ' . $widgetStatus . ' | ' . json_encode($widgetData));
    }

    $widgetAppointmentId = $widgetData['appointment']['id'] ?? null;
    if (!$widgetAppointmentId) {
        throw new RuntimeException('WIDGET booking response missing appointment id.');
    }

    $webAppointment = Appointment::findOrFail($webAppointmentId);
    $widgetAppointment = Appointment::findOrFail($widgetAppointmentId);

    $confirmationSent = Mail::sent(AppointmentConfirmationMail::class);
    $confirmationQueued = Mail::queued(AppointmentConfirmationMail::class);
    $confirmationSentCount = is_array($confirmationSent) ? count($confirmationSent) : $confirmationSent->count();
    $confirmationQueuedCount = is_array($confirmationQueued) ? count($confirmationQueued) : $confirmationQueued->count();

    $result = [
        'generated_at' => Carbon::now('Europe/Sarajevo')->toIso8601String(),
        'smoke_context' => [
            'test_date_europe' => $dateEuro,
            'salon_id' => $salon->id,
            'staff_id' => $staff->id,
            'service_id' => $service->id,
            'widget_api_key' => $widget->api_key,
            'captured_confirmation_sent' => $confirmationSentCount,
            'captured_confirmation_queued' => $confirmationQueuedCount,
        ],
        'web' => buildEmailSnapshot($webAppointment),
        'widget' => buildEmailSnapshot($widgetAppointment),
    ];

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'SMOKE TEST FAILED: ' . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
    exit(1);
} finally {
    DB::rollBack();
}
