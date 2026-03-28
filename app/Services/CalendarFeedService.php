<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class CalendarFeedService
{
    /**
     * Ensure staff has a calendar token and return it.
     */
    public function ensureFeedToken(Staff $staff): string
    {
        if (!$staff->calendar_feed_token) {
            $this->regenerateFeedToken($staff);
            $staff->refresh();
        }

        return (string) $staff->calendar_feed_token;
    }

    /**
     * Regenerate staff calendar token.
     */
    public function regenerateFeedToken(Staff $staff): string
    {
        do {
            $token = Str::random(64);
        } while (Staff::where('calendar_feed_token', $token)->exists());

        $staff->forceFill([
            'calendar_feed_token' => $token,
            'calendar_feed_token_generated_at' => now(),
        ])->save();

        return $token;
    }

    /**
     * Build all calendar import URLs for staff.
     */
    public function getImportLinks(Staff $staff): array
    {
        $token = $this->ensureFeedToken($staff);
        $feedUrl = $this->buildFeedUrl($token);
        $webcalUrl = $this->buildWebcalUrl($feedUrl);
        $calendarName = urlencode('Frizerino - ' . $staff->name);

        return [
            'feed_url' => $feedUrl,
            'webcal_url' => $webcalUrl,
            'google_import_url' => 'https://calendar.google.com/calendar/u/0/r?cid=' . urlencode($feedUrl),
            'outlook_import_url' => 'https://outlook.live.com/calendar/0/addcalendar?url=' . urlencode($feedUrl) . '&name=' . $calendarName,
            'ios_import_url' => $webcalUrl,
            'token_generated_at' => optional($staff->calendar_feed_token_generated_at)->toIso8601String(),
        ];
    }

    /**
     * Build ICS feed for staff appointments.
     */
    public function generateStaffFeedIcs(Staff $staff, Collection $appointments): string
    {
        $timezone = 'Europe/Sarajevo';
        $calendarTitle = 'Frizerino - ' . $staff->name;

        $allMultiServiceIds = $appointments
            ->pluck('service_ids')
            ->filter(fn ($ids) => is_array($ids) && !empty($ids))
            ->flatten()
            ->unique()
            ->values();

        $serviceNameMap = $allMultiServiceIds->isNotEmpty()
            ? Service::whereIn('id', $allMultiServiceIds)->pluck('name', 'id')
            : collect();

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//Frizerino//Staff Calendar Feed//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "X-WR-CALNAME:" . $this->escapeIcsText($calendarTitle) . "\r\n";
        $ics .= "X-WR-TIMEZONE:{$timezone}\r\n";

        foreach ($appointments as $appointment) {
            $event = $this->buildEvent($staff, $appointment, $serviceNameMap, $timezone);

            if ($event === null) {
                continue;
            }

            $ics .= $event;
        }

        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }

    /**
     * Build single VEVENT block.
     */
    private function buildEvent(Staff $staff, Appointment $appointment, Collection $serviceNameMap, string $timezone): ?string
    {
        $dateString = $appointment->date instanceof Carbon
            ? $appointment->date->format('Y-m-d')
            : (string) $appointment->date;

        $startTime = substr((string) $appointment->time, 0, 5);
        $endTime = $appointment->end_time ? substr((string) $appointment->end_time, 0, 5) : null;

        try {
            $start = Carbon::createFromFormat('Y-m-d H:i', "{$dateString} {$startTime}", $timezone);
        } catch (\Throwable) {
            return null;
        }

        $end = null;
        if ($endTime) {
            try {
                $end = Carbon::createFromFormat('Y-m-d H:i', "{$dateString} {$endTime}", $timezone);
            } catch (\Throwable) {
                $end = null;
            }
        }

        if (!$end || $end->lessThanOrEqualTo($start)) {
            $fallbackDuration = max((int) optional($appointment->service)->duration, 30);
            $end = $start->copy()->addMinutes($fallbackDuration);
        }

        $startUtc = $start->copy()->utc();
        $endUtc = $end->copy()->utc();

        $serviceName = $this->resolveServiceName($appointment, $serviceNameMap);
        $salonName = $appointment->salon?->name ?? $staff->salon?->name ?? 'Salon';
        $summary = "{$serviceName} - {$salonName}";

        $status = match ($appointment->status) {
            'cancelled' => 'CANCELLED',
            'pending' => 'TENTATIVE',
            default => 'CONFIRMED',
        };

        $descriptionLines = [
            "Usluga: {$serviceName}",
            'Klijent: ' . ($appointment->client_name ?: 'N/A'),
            'Telefon: ' . ($appointment->client_phone ?: 'N/A'),
            'Status: ' . $appointment->status,
            'Cijena: ' . number_format((float) $appointment->total_price, 2) . ' KM',
        ];

        if (!empty($appointment->notes)) {
            $descriptionLines[] = 'Napomena: ' . $appointment->notes;
        }

        $description = implode("\n", $descriptionLines);
        $location = trim(
            ($appointment->salon?->name ?? '') .
            ($appointment->salon?->address ? ', ' . $appointment->salon?->address : '') .
            ($appointment->salon?->city ? ', ' . $appointment->salon?->city : '')
        );

        $uid = "frizerino-staff-{$staff->id}-appointment-{$appointment->id}@frizerino.com";
        $lastModified = ($appointment->updated_at ?? now())->copy()->utc()->format('Ymd\THis\Z');

        $event = "BEGIN:VEVENT\r\n";
        $event .= "UID:{$uid}\r\n";
        $event .= "DTSTAMP:" . now()->utc()->format('Ymd\THis\Z') . "\r\n";
        $event .= "LAST-MODIFIED:{$lastModified}\r\n";
        $event .= "DTSTART:" . $startUtc->format('Ymd\THis\Z') . "\r\n";
        $event .= "DTEND:" . $endUtc->format('Ymd\THis\Z') . "\r\n";
        $event .= "SUMMARY:" . $this->escapeIcsText($summary) . "\r\n";
        $event .= "DESCRIPTION:" . $this->escapeIcsText($description) . "\r\n";
        $event .= "LOCATION:" . $this->escapeIcsText($location) . "\r\n";
        $event .= "STATUS:{$status}\r\n";
        $event .= "TRANSP:OPAQUE\r\n";
        $event .= "END:VEVENT\r\n";

        return $event;
    }

    /**
     * Resolve service name for single or multi-service appointment.
     */
    private function resolveServiceName(Appointment $appointment, Collection $serviceNameMap): string
    {
        if ($appointment->service?->name) {
            return $appointment->service->name;
        }

        if (is_array($appointment->service_ids) && !empty($appointment->service_ids)) {
            $names = collect($appointment->service_ids)
                ->map(fn ($id) => $serviceNameMap->get($id))
                ->filter()
                ->values();

            if ($names->isNotEmpty()) {
                return $names->implode(', ');
            }
        }

        return 'Termin';
    }

    /**
     * Build absolute feed URL from token.
     */
    private function buildFeedUrl(string $token): string
    {
        return URL::to("/api/v1/public/calendar/staff/{$token}.ics");
    }

    /**
     * Build webcal URL for Apple Calendar subscriptions.
     */
    private function buildWebcalUrl(string $feedUrl): string
    {
        return preg_replace('/^https?:\/\//i', 'webcal://', $feedUrl) ?? $feedUrl;
    }

    /**
     * Escape text for ICS format.
     */
    private function escapeIcsText(?string $text): string
    {
        $value = (string) ($text ?? '');
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(',', '\\,', $value);
        $value = str_replace(';', '\\;', $value);
        $value = str_replace("\n", '\\n', $value);
        $value = str_replace("\r", '', $value);

        return $value;
    }
}
