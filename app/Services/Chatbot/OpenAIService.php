<?php

namespace App\Services\Chatbot;

use OpenAI\Client as OpenAIClient;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    private ?OpenAIClient $client = null;
    private array $config;
    private bool $enabled = false;

    public function __construct()
    {
        $this->config = config('chatbot.openai');

        $apiKey = config('chatbot.openai.api_key');
        if (is_string($apiKey) && trim($apiKey) !== '') {
            $this->client = \OpenAI::client($apiKey);
            $this->enabled = true;
        } else {
            Log::warning('OpenAI API key is missing. Chatbot AI responses are disabled.');
        }
    }

    /**
     * Analyze user message and extract intent + entities
     */
    public function analyzeMessage(string $message, array $context): array
    {
        if (!$this->enabled || !$this->client) {
            return [
                'intent' => 'general',
                'confidence' => 0.4,
                'entities' => [],
                'next_action' => 'fallback',
                'processing_time_ms' => 0,
            ];
        }

        $systemPrompt = $this->buildSystemPrompt($context);
        $userPrompt = $this->buildAnalysisPrompt($message, $context);

        $startTime = microtime(true);

        try {
            $response = $this->client->chat()->create([
                'model' => $this->config['model'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.3, // Lower for classification
                'max_tokens' => 300,
                'response_format' => ['type' => 'json_object'],
            ]);

            $result = json_decode($response->choices[0]->message->content, true);
            $processingTime = (microtime(true) - $startTime) * 1000;

            return [
                'intent' => $result['intent'] ?? 'unknown',
                'confidence' => $result['confidence'] ?? 0.5,
                'entities' => $result['entities'] ?? [],
                'next_action' => $result['next_action'] ?? 'ask_clarification',
                'processing_time_ms' => round($processingTime),
            ];

        } catch (\Exception $e) {
            Log::error('OpenAI analysis failed', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);

            return [
                'intent' => 'error',
                'confidence' => 0.0,
                'entities' => [],
                'next_action' => 'fallback',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate natural response based on context and action
     */
    public function generateResponse(array $context, string $action, array $data = []): string
    {
        if (!$this->enabled || !$this->client) {
            return $this->getFallbackResponse($action);
        }

        $systemPrompt = $this->buildResponseSystemPrompt($context);
        $userPrompt = $this->buildResponsePrompt($action, $data, $context);

        try {
            $response = $this->client->chat()->create([
                'model' => $this->config['model'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => $this->config['temperature'],
                'max_tokens' => $this->config['max_tokens'],
            ]);

            return trim($response->choices[0]->message->content);

        } catch (\Exception $e) {
            Log::error('OpenAI response generation failed', [
                'error' => $e->getMessage(),
                'action' => $action,
            ]);

            // Fallback to template
            return $this->getFallbackResponse($action);
        }
    }

    private function buildSystemPrompt(array $context): string
    {
        $salon = $context['salon'];

        return <<<PROMPT
Ti si profesionalni asistent za salon "{$salon['name']}".

TVOJA ULOGA:
- Analiziraj korisničku poruku i odredi namjeru (intent)
- Izvuci relevantne podatke (entities)
- Predloži sljedeći korak u razgovoru

INTENTS (namjere):
- booking: Korisnik želi zakazati termin
- pricing: Pita za cijene usluga
- hours: Pita za radno vrijeme
- location: Pita gdje se nalazi salon
- cancellation: Želi otkazati/promijeniti termin
- general: Opšta pitanja

ENTITIES (podaci za izvući):
- service: Naziv usluge (šišanje, feniranje, bojenje, itd.)
- date: Željeni datum (danas, sutra, konkretni datum)
- time: Željeno vrijeme (popodne, 14:00, itd.)
- staff: Ime frizera (ako spomenuto)
- urgency: Da li je hitno

OUTPUT FORMAT (JSON):
{
  "intent": "booking|pricing|hours|location|cancellation|general",
  "confidence": 0.0-1.0,
  "entities": {
    "service": "string ili null",
    "date": "string ili null",
    "time": "string ili null",
    "staff": "string ili null"
  },
  "next_action": "ask_service|ask_date|ask_time|provide_info|confirm_booking"
}

PRAVILA:
- Budi precizan, ne nagađaj
- Ako nisi siguran, stavi confidence < 0.7
- Izvuci samo ono što je eksplicitno spomenuto
PROMPT;
    }

    private function buildAnalysisPrompt(string $message, array $context): string
    {
        $conversationState = $context['conversation_state'] ?? 'new';
        $previousContext = json_encode($context['previous_context'] ?? [], JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
PORUKA KORISNIKA: "{$message}"

TRENUTNO STANJE RAZGOVORA: {$conversationState}
PRETHODNI KONTEKST: {$previousContext}

Analiziraj poruku i vrati JSON sa intent, confidence, entities i next_action.
PROMPT;
    }

    private function buildResponseSystemPrompt(array $context): string
    {
        $salon = $context['salon'];
        $tone = $context['tone'] ?? 'friendly_professional';

        return <<<PROMPT
Ti si AI asistent za salon "{$salon['name']}".

TON KOMUNIKACIJE: {$tone}
- Prijateljski ali profesionalan
- Kratak i jasan
- Koristi ijekavicu (gdje, vrijeme, lijepo)
- Bez emojija osim ako korisnik koristi

PRAVILA:
- NIKADA ne izmišljaj termine, cijene ili informacije
- Koristi SAMO podatke koje ti pošaljem
- Ako nešto ne znaš, reci da ćeš provjeriti
- Budi koncizan (max 2-3 rečenice)

INFORMACIJE O SALONU:
Naziv: {$salon['name']}
Lokacija: {$salon['address']}
Telefon: {$salon['phone']}
PROMPT;
    }

    private function buildResponsePrompt(string $action, array $data, array $context): string
    {
        // Different prompts based on action
        return match($action) {
            'greet' => "Pozdrav novi korisnik. Predstavi salon i pitaj kako možeš pomoći.",
            'ask_service' => "Korisnik želi zakazati termin. Pitaj koju uslugu želi. Dostupne usluge: " . implode(', ', $data['services'] ?? []),
            'ask_date' => "Korisnik je odabrao uslugu: {$data['service']}. Pitaj za željeni datum.",
            'ask_time' => "Korisnik želi termin {$data['date']}. Ponudi dostupne termine: " . implode(', ', $data['available_slots'] ?? []),
            'provide_pricing' => "Korisnik pita za cijene. Usluge i cijene: " . json_encode($data['pricing'] ?? [], JSON_UNESCAPED_UNICODE),
            'provide_hours' => "Korisnik pita za radno vrijeme: " . json_encode($data['hours'] ?? [], JSON_UNESCAPED_UNICODE),
            'confirm_booking' => "Potvrdi rezervaciju: {$data['service']} dana {$data['date']} u {$data['time']}. Traži ime i telefon.",
            'booking_success' => "Rezervacija uspješna! Detalji: " . json_encode($data, JSON_UNESCAPED_UNICODE),
            default => "Odgovori na poruku korisnika na prirodan način.",
        };
    }

    private function getFallbackResponse(string $action): string
    {
        return match($action) {
            'greet' => "Zdravo! Dobrodošli u naš salon. Kako vam mogu pomoći?",
            'ask_service' => "Koju uslugu želite zakazati?",
            'error' => "Izvinite, trenutno imam tehničkih poteškoća. Molim vas kontaktirajte nas telefonom.",
            default => "Hvala na poruci. Uskoro ćemo vam odgovoriti.",
        };
    }
}
