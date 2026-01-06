<?php

namespace App\Mail;

use App\Models\Salon;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Salon $salon;
    public array $reportData;
    public Carbon $date;

    /**
     * Create a new message instance.
     */
    public function __construct(Salon $salon, array $reportData, Carbon $date)
    {
        $this->salon = $salon;
        $this->reportData = $reportData;
        $this->date = $date;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('info@frizerino.com', 'Frizerino'),
            subject: "Dnevni izvještaj - {$this->date->format('d.m.Y')} - {$this->salon->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-report',
            with: [
                'salon' => $this->salon,
                'report' => $this->reportData,
                'date' => $this->date,
            ],
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
