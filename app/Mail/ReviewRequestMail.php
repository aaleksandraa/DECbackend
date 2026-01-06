<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Appointment $appointment;
    public string $salonName;
    public string $salonSlug;
    public string $serviceName;
    public string $staffName;
    public string $clientName;
    public string $reviewUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment;
        $this->salonName = $appointment->salon->name;
        $this->salonSlug = $appointment->salon->slug;
        $this->serviceName = $appointment->service->name;
        $this->staffName = $appointment->staff->name;
        $this->clientName = $appointment->client->name ?? $appointment->client_name ?? 'Klijent';

        // Build review URL - frontend URL with salon slug and review parameter
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $this->reviewUrl = "{$frontendUrl}/salon/{$this->salonSlug}?writeReview=true";
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
            from: new \Illuminate\Mail\Mailables\Address('info@frizerino.com', $salon->name),
            replyTo: [new \Illuminate\Mail\Mailables\Address($replyToEmail, $replyToName)],
            subject: "Ocijenite Vaše iskustvo u salonu {$this->salonName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.review-request',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
