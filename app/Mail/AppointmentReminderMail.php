<?php

namespace App\Mail;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Appointment $appointment;
    public string $reminderType;
    public string $formattedDate;
    public string $formattedTime;
    public string $endTime;
    public string $hoursUntil;

    /**
     * Create a new message instance.
     */
    public function __construct(Appointment $appointment, string $reminderType = 'day_before')
    {
        $this->appointment = $appointment->load(['salon', 'service', 'staff', 'client']);
        $this->reminderType = in_array($reminderType, ['day_before', 'same_day'], true)
            ? $reminderType
            : 'day_before';

        // Parse date and time
        $dateString = $appointment->date instanceof Carbon
            ? $appointment->date->format('Y-m-d')
            : $appointment->date;
        $startDateTime = Carbon::parse($dateString.' '.$appointment->time, 'Europe/Sarajevo');
        $duration = $appointment->service->duration ?? 60;
        $endDateTime = $startDateTime->copy()->addMinutes($duration);

        $this->formattedDate = $startDateTime->locale('bs')->isoFormat('dddd, D. MMMM YYYY.');
        $this->formattedTime = $startDateTime->format('H:i');
        $this->endTime = $endDateTime->format('H:i');

        // Calculate dynamic reminder label based on reminder type.
        $now = Carbon::now('Europe/Sarajevo');
        $this->hoursUntil = $this->buildReminderLabel($startDateTime, $now);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $salon = $this->appointment->salon;

        // Use salon's email for Reply-To if available
        $replyToEmail = $salon->email ?: 'info@frizerino.com';
        $replyToName = $salon->email ? $salon->name : 'Frizerino Podrska';

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address('info@frizerino.com', 'Frizerino'),
            replyTo: [new \Illuminate\Mail\Mailables\Address($replyToEmail, $replyToName)],
            subject: 'Podsjetnik: Vas termin '.$this->hoursUntil,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.appointment-reminder',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }

    private function buildReminderLabel(Carbon $startDateTime, Carbon $now): string
    {
        if ($this->reminderType === 'day_before') {
            return 'sutra';
        }

        $minutesUntil = $now->diffInMinutes($startDateTime, false);

        if ($minutesUntil <= 0) {
            return 'danas';
        }

        if ($minutesUntil < 60) {
            return 'za '.$minutesUntil.' min';
        }

        $hoursUntil = (int) ceil($minutesUntil / 60);

        return $hoursUntil === 1 ? 'za 1 sat' : 'za '.$hoursUntil.' sati';
    }
}
