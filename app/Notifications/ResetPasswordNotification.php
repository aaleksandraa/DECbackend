<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The password reset token.
     */
    public string $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $resetUrl = $frontendUrl . '/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('Resetovanje lozinke - Frizerino')
            ->greeting('Pozdrav ' . $notifiable->name . '!')
            ->line('Primili smo zahtjev za resetovanje lozinke vašeg naloga.')
            ->action('Resetuj lozinku', $resetUrl)
            ->line('Ovaj link za resetovanje lozinke vrijedi 60 minuta.')
            ->line('Ako niste zatražili resetovanje lozinke, možete ignorisati ovaj email.')
            ->salutation('Srdačan pozdrav, Frizerino tim');
    }
}
