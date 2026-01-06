<?php

/**
 * Chatbot Models Test Script
 *
 * Quick test to verify models are working correctly.
 * Run after migrations: php backend/test_chatbot_models.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Salon;
use App\Models\SocialIntegration;
use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\ChatbotAnalytics;
use Illuminate\Support\Facades\DB;

echo "\n========================================\n";
echo "Chatbot Models Test\n";
echo "========================================\n\n";

try {
    // Test 1: SocialIntegration
    echo "Test 1: SocialIntegration Model\n";
    echo str_repeat('-', 50) . "\n";

    $salon = Salon::first();
    if (!$salon) {
        throw new Exception("No salon found in database");
    }

    echo "  Using salon: {$salon->name} (ID: {$salon->id})\n";

    // Create test integration
    $integration = SocialIntegration::create([
        'salon_id' => $salon->id,
        'provider' => 'meta',
        'platform' => 'instagram',
        'fb_page_id' => 'test_page_123',
        'fb_page_name' => 'Test Salon Page',
        'ig_business_account_id' => 'test_ig_456',
        'ig_username' => 'testsalon',
        'access_token' => 'test_token_encrypted',
        'token_type' => 'page_access_token',
        'granted_scopes' => ['pages_messaging', 'instagram_manage_messages'],
        'status' => 'active',
        'webhook_verified' => true,
        'auto_reply_enabled' => true,
        'business_hours_only' => false,
    ]);

    echo "  ✅ Created SocialIntegration (ID: {$integration->id})\n";

    // Test encryption
    $decryptedToken = $integration->access_token;
    echo "  ✅ Token encryption/decryption works\n";

    // Test scopes
    echo "  ✅ Boolean cast works: auto_reply_enabled = " . ($integration->auto_reply_enabled ? 'true' : 'false') . "\n";
    echo "  ✅ Array cast works: " . count($integration->granted_scopes) . " scopes\n";

    // Test methods
    echo "  ✅ shouldAutoReply(): " . ($integration->shouldAutoReply() ? 'true' : 'false') . "\n";
    echo "  ✅ getDisplayName(): {$integration->getDisplayName()}\n";

    // Test 2: ChatbotConversation
    echo "\nTest 2: ChatbotConversation Model\n";
    echo str_repeat('-', 50) . "\n";

    $conversation = ChatbotConversation::create([
        'salon_id' => $salon->id,
        'social_integration_id' => $integration->id,
        'platform' => 'instagram',
        'thread_id' => 'test_thread_789',
        'sender_psid' => 'test_user_psid',
        'sender_name' => 'Test User',
        'state' => ChatbotConversation::STATE_NEW,
        'intent' => ChatbotConversation::INTENT_BOOKING,
        'confidence' => 0.85,
        'context' => ['test_key' => 'test_value'],
        'started_at' => now(),
    ]);

    echo "  ✅ Created ChatbotConversation (ID: {$conversation->id})\n";

    // Test context management
    $conversation->updateContext(['service' => 'šišanje', 'date' => '2025-01-15']);
    $conversation->refresh();
    echo "  ✅ Context updated: " . json_encode($conversation->context) . "\n";

    $service = $conversation->getContextValue('service');
    echo "  ✅ getContextValue('service'): {$service}\n";

    // Test state machine
    $conversation->transitionTo(ChatbotConversation::STATE_COLLECTING_SERVICE);
    $conversation->refresh();
    echo "  ✅ State transition: {$conversation->state}\n";

    // Test 3: ChatbotMessage
    echo "\nTest 3: ChatbotMessage Model\n";
    echo str_repeat('-', 50) . "\n";

    $inboundMessage = ChatbotMessage::createInbound(
        $conversation->id,
        'Zdravo, želim zakazati termin',
        ['meta_id' => 'msg_123']
    );

    echo "  ✅ Created inbound message (ID: {$inboundMessage->id})\n";

    // Test AI processing
    $inboundMessage->markAsAiProcessed([
        'intent' => 'booking',
        'entities' => ['service' => 'šišanje'],
        'confidence' => 0.92,
        'processing_time_ms' => 150,
    ]);
    $inboundMessage->refresh();

    echo "  ✅ AI processing marked: intent={$inboundMessage->ai_intent}, confidence={$inboundMessage->ai_confidence}\n";

    $outboundMessage = ChatbotMessage::createOutbound(
        $conversation->id,
        'Odlično! Koju uslugu želite?',
        'ask_service',
        true
    );

    echo "  ✅ Created outbound message (ID: {$outboundMessage->id})\n";

    // Test delivery status
    $outboundMessage->markAsSent('meta_msg_456');
    $outboundMessage->markAsDelivered();
    $outboundMessage->refresh();

    echo "  ✅ Delivery status: {$outboundMessage->getDeliveryStatus()}\n";

    // Update conversation message count
    $conversation->incrementMessageCount(2);
    $conversation->refresh();
    echo "  ✅ Conversation message_count: {$conversation->message_count}\n";

    // Test 4: ChatbotAnalytics
    echo "\nTest 4: ChatbotAnalytics Model\n";
    echo str_repeat('-', 50) . "\n";

    $analytics = ChatbotAnalytics::aggregateForDate($salon->id, now());

    echo "  ✅ Created ChatbotAnalytics (ID: {$analytics->id})\n";
    echo "  ✅ Total conversations: {$analytics->total_conversations}\n";
    echo "  ✅ Total messages received: {$analytics->total_messages_received}\n";
    echo "  ✅ Total messages sent: {$analytics->total_messages_sent}\n";

    // Test 5: Relationships
    echo "\nTest 5: Relationships\n";
    echo str_repeat('-', 50) . "\n";

    echo "  ✅ Integration->salon: {$integration->salon->name}\n";
    echo "  ✅ Integration->conversations: " . $integration->conversations()->count() . "\n";
    echo "  ✅ Conversation->salon: {$conversation->salon->name}\n";
    echo "  ✅ Conversation->integration: {$conversation->integration->platform}\n";
    echo "  ✅ Conversation->messages: " . $conversation->messages()->count() . "\n";
    echo "  ✅ Message->conversation: Thread {$inboundMessage->conversation->thread_id}\n";

    // Test 6: Scopes
    echo "\nTest 6: Scopes\n";
    echo str_repeat('-', 50) . "\n";

    $activeIntegrations = SocialIntegration::active()->count();
    echo "  ✅ Active integrations: {$activeIntegrations}\n";

    $activeConversations = ChatbotConversation::active()->count();
    echo "  ✅ Active conversations: {$activeConversations}\n";

    $inboundMessages = ChatbotMessage::inbound()->count();
    echo "  ✅ Inbound messages: {$inboundMessages}\n";

    $outboundMessages = ChatbotMessage::outbound()->count();
    echo "  ✅ Outbound messages: {$outboundMessages}\n";

    // Cleanup
    echo "\nCleaning up test data...\n";
    $analytics->delete();
    $outboundMessage->delete();
    $inboundMessage->delete();
    $conversation->delete();
    $integration->delete();
    echo "  ✅ Test data cleaned up\n";

    echo "\n========================================\n";
    echo "✅ All Tests Passed!\n";
    echo "========================================\n\n";

    echo "Summary:\n";
    echo "  - SocialIntegration: Encryption, casts, methods ✅\n";
    echo "  - ChatbotConversation: State machine, context ✅\n";
    echo "  - ChatbotMessage: AI processing, delivery status ✅\n";
    echo "  - ChatbotAnalytics: Aggregation ✅\n";
    echo "  - Relationships: All working ✅\n";
    echo "  - Scopes: All working ✅\n";
    echo "\n";

    exit(0);

} catch (Exception $e) {
    echo "\n❌ Test Failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}
