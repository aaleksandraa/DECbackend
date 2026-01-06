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

class AppointmentReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Appointment $appointment;
    public string $formattedDate;
    public string $formattedTime;
    public string $endTime;
    public string $hoursUntil;

    /**
     * Create a new message instance.
     */
    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment->load(['salon', 'service', 'staff', 'client']);

        // Parse date and time
        $dateString = $appointment->date instanceof Carbon
            ? $appointment->date->format('Y-m-d')
            : $appointment->date;
        $startDateTime = Carbon::parse($dateString . ' ' . $appointment->time);
        $duration = $appointment->service->duration ?? 60;
        $endDateTime = $startDateTime->copy()->addMinutes($duration);

        $this->formattedDate = $startDateTime->locale('bs')->isoFormat('dddd, D. MMMM YYYY.');
        $this->formattedTime = $startDateTime->format('H:i');
        $this->endTime = $endDateTime->format('H:i');

        // Calculate hours until appointment
        $now = Carbon::now();
        $hoursUntil = $now->diffInHours($startDateTime);
        $this->hoursUntil = $hoursUntil > 24 ? 'sutra' : "za {$hoursUntil} sati";
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $salon = $this->appointment->salon;

        // Use salon's email for Reply-To if available
        $replyToEmail = $salon->email ?: 'info@frizerino.com';
        $replyToName = $salon->email ? $salon->name : 'Frizerino Podrška';

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address('info@frizerino.com', 'Frizerino'),
            replyTo: [new \Illuminate\Mail\Mailables\Address($replyToEmail, $replyToName)],
            subject: 'Podsjetnik: Vaš termin ' . $this->hoursUntil,
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
}
