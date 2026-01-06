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

class AppointmentCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Appointment $appointment;
    public string $formattedDate;
    public string $formattedTime;
    public string $recipientType; // 'client' or 'salon'
    public string $cancelledBy;

    /**
     * Create a new message instance.
     */
    public function __construct(Appointment $appointment, string $recipientType = 'client', string $cancelledBy = 'client')
    {
        $this->appointment = $appointment->load(['salon', 'service', 'staff']);
        $this->recipientType = $recipientType;
        $this->cancelledBy = $cancelledBy;

        // Parse date and time
        $dateString = $appointment->date instanceof Carbon
            ? $appointment->date->format('Y-m-d')
            : $appointment->date;
        $startDateTime = Carbon::parse($dateString . ' ' . $appointment->time);

        $this->formattedDate = $startDateTime->locale('bs')->isoFormat('dddd, D. MMMM YYYY.');
        $this->formattedTime = $startDateTime->format('H:i');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $salon = $this->appointment->salon;

        $subject = $this->recipientType === 'client'
            ? 'Termin otkazan - ' . $salon->name
            : 'Otkazan termin - ' . ($this->appointment->client_name ?? 'Klijent');

        // Use salon's email for Reply-To if available
        $replyToEmail = $salon->email ?: 'info@frizerino.com';
        $replyToName = $salon->email ? $salon->name : 'Frizerino Podrška';

        // For client emails, use salon branding
        if ($this->recipientType === 'client') {
            return new Envelope(
                from: new \Illuminate\Mail\Mailables\Address('info@frizerino.com', $salon->name),
                replyTo: [new \Illuminate\Mail\Mailables\Address($replyToEmail, $replyToName)],
                subject: $subject,
            );
        }

        // For salon emails, use Frizerino branding
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address('info@frizerino.com', 'Frizerino'),
            replyTo: [new \Illuminate\Mail\Mailables\Address('info@frizerino.com', 'Frizerino Podrška')],
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.appointment-cancelled',
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
