<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    /**
     * Send contact form email
     */
    public function send(Request $request): JsonResponse
    {
        // Anti-spam: Check for honeypot field
        if ($request->has('honeypot') && !empty($request->input('honeypot'))) {
            // Bot detected - return success to avoid revealing detection
            return response()->json([
                'message' => 'Poruka uspješno poslana. Odgovorit ćemo vam u najkraćem roku.'
            ], 200);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|min:10|max:5000',
        ], [
            'name.required' => 'Ime je obavezno',
            'email.required' => 'Email je obavezan',
            'email.email' => 'Email mora biti validna email adresa',
            'message.required' => 'Poruka je obavezna',
            'message.min' => 'Poruka mora imati najmanje 10 karaktera',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validacija nije uspjela',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        try {
            // Send email to support
            Mail::send('emails.contact', $data, function ($message) use ($data) {
                $message->from('info@frizerino.com', 'Frizerino')
                    ->to('podrska@frizerino.com')
                    ->subject('Kontakt forma - ' . ($data['subject'] ?: 'Opšto pitanje'))
                    ->replyTo($data['email'], $data['name']);
            });

            // Send confirmation email to user
            Mail::send('emails.contact-confirmation', $data, function ($message) use ($data) {
                $message->from('info@frizerino.com', 'Frizerino')
                    ->to($data['email'], $data['name'])
                    ->subject('Hvala na poruci - Frizerino');
            });

            return response()->json([
                'message' => 'Poruka uspješno poslana. Odgovorit ćemo vam u najkraćem roku.'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Contact form error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Greška pri slanju poruke. Molimo pokušajte ponovo ili nas kontaktirajte direktno na podrska@frizerino.com'
            ], 500);
        }
    }
}
