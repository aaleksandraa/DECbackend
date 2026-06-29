<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendClientCampaignEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public int $salonId,
        public int $clientId,
        public string $subjectTemplate,
        public string $messageTemplate,
        public string $fromName
    ) {
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(): void
    {
        $client = User::find($this->clientId);

        if (!$client || empty($client->email)) {
            return;
        }

        $belongsToSalon = Appointment::query()
            ->where('salon_id', $this->salonId)
            ->where('client_id', $this->clientId)
            ->where('status', '!=', 'cancelled')
            ->exists();

        if (!$belongsToSalon) {
            Log::warning('Skipped client campaign email because client no longer belongs to salon', [
                'salon_id' => $this->salonId,
                'client_id' => $this->clientId,
            ]);
            return;
        }

        $subject = $this->personalizeTemplate($this->subjectTemplate, $client);
        $message = $this->personalizeTemplate($this->messageTemplate, $client);

        Mail::raw($message, function ($mail) use ($client, $subject) {
            $mail->to($client->email)
                ->subject($subject)
                ->from((string) config('mail.from.address'), $this->fromName);
        });

        Log::info('Client campaign email sent', [
            'salon_id' => $this->salonId,
            'client_id' => $this->clientId,
        ]);
    }

    private function personalizeTemplate(string $template, User $client): string
    {
        $fullName = trim((string) $client->name);
        $firstName = $fullName !== '' ? explode(' ', $fullName)[0] : 'klijente';

        return strtr($template, [
            '{ime}' => $firstName,
            '{{ime}}' => $firstName,
            '{korisnicko_ime}' => $fullName,
            '{{korisnicko_ime}}' => $fullName,
            '{name}' => $fullName,
            '{{name}}' => $fullName,
        ]);
    }
}
