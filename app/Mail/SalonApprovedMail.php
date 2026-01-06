<?php

namespace App\Mail;

use App\Models\Salon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SalonApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Salon $salon;

    /**
     * Create a new message instance.
     */
    public function __construct(Salon $salon)
    {
        $this->salon = $salon;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address('info@frizerino.com', 'Frizerino'),
            replyTo: [new \Illuminate\Mail\Mailables\Address('info@frizerino.com', 'Frizerino Podrška')],
            subject: 'Vaš salon je odobren! 🎉 - Frizerino',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.salon-approved',
            with: [
                'salon' => $this->salon,
                'ownerName' => $this->salon->owner?->name ?? 'Vlasnik',
                'dashboardUrl' => config('app.frontend_url', 'https://frizerino.com') . '/dashboard',
            ],
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
