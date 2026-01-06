<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaService
{
    private string $graphApiUrl;
    private string $apiVersion;

    public function __construct()
    {
        $this->apiVersion = config('chatbot.meta.graph_api_version');
        $this->graphApiUrl = "https://graph.facebook.com/{$this->apiVersion}";
    }

    /**
     * Send message to user via Meta Graph API
     */
    public function sendMessage(string $recipientPsid, string $messageText, string $accessToken): array
    {
        $url = "{$this->graphApiUrl}/me/messages";

        try {
            $response = Http::timeout(10)
                ->withToken($accessToken)
                ->post($url, [
                    'recipient' => ['id' => $recipientPsid],
                    'message' => ['text' => $messageText],
                    'messaging_type' => 'RESPONSE',
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message_id' => $response->json('message_id'),
                    'recipient_id' => $response->json('recipient_id'),
                ];
            }

            Log::error('Meta API send message failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => $response->json('error.message', 'Unknown error'),
            ];

        } catch (\Exception $e) {
            Log::error('Meta API exception', [
                'error' => $e->getMessage(),
                'recipient' => $recipientPsid,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $signature, string $payload): bool
    {
        $appSecret = config('chatbot.meta.app_secret');
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Exchange short-lived token for long-lived token
     */
    public function exchangeToken(string $shortLivedToken): array
    {
        $url = "{$this->graphApiUrl}/oauth/access_token";

        $response = Http::get($url, [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('chatbot.meta.app_id'),
            'client_secret' => config('chatbot.meta.app_secret'),
            'fb_exchange_token' => $shortLivedToken,
        ]);

        if ($response->successful()) {
            return [
                'access_token' => $response->json('access_token'),
                'token_type' => $response->json('token_type'),
                'expires_in' => $response->json('expires_in'),
            ];
        }

        throw new \Exception('Token exchange failed: ' . $response->body());
    }

    /**
     * Get page info
     */
    public function getPageInfo(string $pageId, string $accessToken): array
    {
        $url = "{$this->graphApiUrl}/{$pageId}";

        $response = Http::withToken($accessToken)->get($url, [
            'fields' => 'id,name,username,instagram_business_account',
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Failed to get page info: ' . $response->body());
    }

    /**
     * Subscribe app to page
     */
    public function subscribeApp(string $pageId, string $accessToken): bool
    {
        $url = "{$this->graphApiUrl}/{$pageId}/subscribed_apps";

        $response = Http::withToken($accessToken)->post($url, [
            'subscribed_fields' => ['messages', 'messaging_postbacks', 'message_reads'],
        ]);

        return $response->successful();
    }
}
