<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class AppointmentConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    private const BOOKING_TIMEZONE = 'Europe/Sarajevo';

    public Appointment $appointment;
    public string $googleCalendarUrl;
    public string $outlookCalendarUrl;
    public string $icsContent;
    public string $formattedDate;
    public string $formattedTime;
    public string $endTime;
    public float $totalPrice;
    public int $totalDuration;

    /**
     * Create a new message instance.
     */
    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment->load(['salon', 'service', 'staff']);

        // Get all services for this appointment
        $services = $appointment->services();

        // Calculate total duration and price
        $this->totalDuration = $services->sum('duration');
        $this->totalPrice = $appointment->total_price;

        // Parse date and time - date is already Carbon instance from model cast
        $dateString = $appointment->date instanceof Carbon
            ? $appointment->date->format('Y-m-d')
            : $appointment->date;
        $startTime = substr((string) $appointment->time, 0, 5);
        $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $dateString . ' ' . $startTime, self::BOOKING_TIMEZONE);
        if ($startDateTime === false) {
            $startDateTime = Carbon::parse($dateString . ' ' . $startTime, self::BOOKING_TIMEZONE);
        }
        $endDateTime = $startDateTime->copy()->addMinutes($this->totalDuration);

        $this->formattedDate = $startDateTime->locale('bs')->isoFormat('dddd, D. MMMM YYYY.');
        $this->formattedTime = $startDateTime->format('H:i');
        $this->endTime = $endDateTime->format('H:i');

        // Generate Google Calendar URL
        $this->googleCalendarUrl = $this->generateGoogleCalendarUrl($startDateTime, $endDateTime);
        $this->outlookCalendarUrl = $this->generateOutlookCalendarUrl($startDateTime, $endDateTime);

        // Generate ICS content for iOS/Outlook
        $this->icsContent = $this->generateIcsContent($startDateTime, $endDateTime);
    }

    /**
     * Generate Google Calendar URL
     */
    private function generateGoogleCalendarUrl(Carbon $start, Carbon $end): string
    {
        [$title, $details] = $this->buildCalendarTexts();
        $location = $this->buildCalendarLocation();
        $startFormatted = $start->copy()->utc()->format('Ymd\THis\Z');
        $endFormatted = $end->copy()->utc()->format('Ymd\THis\Z');
        $timezone = urlencode(self::BOOKING_TIMEZONE);

        return "https://calendar.google.com/calendar/render?action=TEMPLATE" .
            "&text=" . urlencode($title) .
            "&dates={$startFormatted}/{$endFormatted}" .
            "&details=" . urlencode($details) .
            "&location=" . urlencode($location) .
            "&ctz={$timezone}" .
            "&sf=true&output=xml";
    }

    /**
     * Generate Outlook Calendar URL.
     */
    private function generateOutlookCalendarUrl(Carbon $start, Carbon $end): string
    {
        [$title, $details] = $this->buildCalendarTexts();
        $location = $this->buildCalendarLocation();

        return "https://outlook.live.com/calendar/0/deeplink/compose" .
            "?subject=" . urlencode($title) .
            "&location=" . urlencode($location) .
            "&body=" . urlencode($details) .
            "&startdt=" . urlencode($start->format('Y-m-d\TH:i:s')) .
            "&enddt=" . urlencode($end->format('Y-m-d\TH:i:s'));
    }

    /**
     * Generate ICS file content for iOS/Outlook
     */
    private function generateIcsContent(Carbon $start, Carbon $end): string
    {
        [$summary, $description] = $this->buildCalendarTexts();

        $uid = $this->appointment->id
            ? 'appointment-' . $this->appointment->id . '@frizerino.com'
            : uniqid('frizerino-') . '@frizerino.com';
        $now = Carbon::now('UTC')->format('Ymd\THis\Z');
        $startFormatted = $start->format('Ymd\THis');
        $endFormatted = $end->format('Ymd\THis');
        $timezone = self::BOOKING_TIMEZONE;

        $location = $this->buildCalendarLocation();

        return "BEGIN:VCALENDAR\r\n" .
            "VERSION:2.0\r\n" .
            "PRODID:-//Frizerino//Appointment//BS\r\n" .
            "CALSCALE:GREGORIAN\r\n" .
            "METHOD:PUBLISH\r\n" .
            "X-WR-TIMEZONE:{$timezone}\r\n" .
            "BEGIN:VEVENT\r\n" .
            "UID:{$uid}\r\n" .
            "DTSTAMP:{$now}\r\n" .
            "DTSTART;TZID={$timezone}:{$startFormatted}\r\n" .
            "DTEND;TZID={$timezone}:{$endFormatted}\r\n" .
            "SUMMARY:" . $this->escapeIcsText($summary) . "\r\n" .
            "DESCRIPTION:" . $this->escapeIcsText($description) . "\r\n" .
            "LOCATION:" . $this->escapeIcsText($location) . "\r\n" .
            "STATUS:CONFIRMED\r\n" .
            "END:VEVENT\r\n" .
            "END:VCALENDAR";
    }

    /**
     * Build shared title and description for all calendar links/attachments.
     *
     * @return array{0: string, 1: string}
     */
    private function buildCalendarTexts(): array
    {
        $salon = $this->appointment->salon;
        $staff = $this->appointment->staff;
        $services = $this->appointment->services();

        if ($services->count() > 1) {
            $serviceList = implode(', ', $services->pluck('name')->toArray());
            $title = "Termin: {$serviceList} - {$salon->name}";
            $details = "Usluge: {$serviceList}\nSalon: {$salon->name}";
        } else {
            $serviceName = $services->first()?->name ?? ($this->appointment->service?->name ?? 'Termin');
            $title = "Termin: {$serviceName} - {$salon->name}";
            $details = "Usluga: {$serviceName}\nSalon: {$salon->name}";
        }

        if ($staff) {
            $details .= "\nFrizer: {$staff->name}";
        }

        $details .= "\n\nRezervisano preko frizerino.com";

        return [$title, $details];
    }

    /**
     * Build location string for calendar entries.
     */
    private function buildCalendarLocation(): string
    {
        return implode(', ', array_filter([
            $this->appointment->salon->address,
            $this->appointment->salon->city,
        ]));
    }

    /**
     * Escape ICS reserved characters.
     */
    private function escapeIcsText(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(',', '\,', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace("\r", '', $text);

        return str_replace("\n", '\n', $text);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $salon = $this->appointment->salon;

        // Use salon's email for Reply-To if available, otherwise fallback to Frizerino support
        $replyToEmail = $salon->email ?: 'info@frizerino.com';
        $replyToName = $salon->email ? $salon->name : 'Frizerino Podrška';

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address('info@frizerino.com', $salon->name),
            replyTo: [new \Illuminate\Mail\Mailables\Address($replyToEmail, $replyToName)],
            subject: 'Potvrda termina - ' . $salon->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.appointment-confirmation',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(fn () => $this->icsContent, 'termin.ics')
                ->withMime('text/calendar'),
        ];
    }
}
