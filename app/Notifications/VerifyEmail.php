<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmail extends BaseVerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Get the verification URL for the given notifiable.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function verificationUrl($notifiable)
    {
        // Generate signed URL that points to frontend
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        // Extract the path and query from the signed URL
        $parsedUrl = parse_url($signedUrl);
        $path = $parsedUrl['path'] ?? '';
        $query = $parsedUrl['query'] ?? '';

        // Build frontend verification URL
        return $frontendUrl . '/verify-email?url=' . urlencode($signedUrl);
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Potvrdite vašu email adresu - Frizerino')
            ->greeting('Pozdrav ' . $notifiable->name . '!')
            ->line('Hvala vam što ste se registrovali na Frizerino platformu.')
            ->line('Molimo vas da potvrdite vašu email adresu klikom na dugme ispod.')
            ->action('Potvrdi email adresu', $verificationUrl)
            ->line('Ako niste kreirali nalog na Frizerino platformi, ignorišite ovaj email.')
            ->salutation('Srdačan pozdrav, Frizerino tim');
    }
}
