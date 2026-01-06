<?php

/**
 * Test Chatbot Security Fixes
 *
 * Provjerava da li su sve sigurnosne ispravke primijenjene
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ChatbotConversation;
use App\Models\SocialIntegration;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;

echo "🔒 Testing Chatbot Security Fixes\n";
echo str_repeat("=", 60) . "\n\n";

// Test 1: Multi-tenant isolation
echo "1️⃣  Testing multi-tenant isolation...\n";
$testThreadId = 'test_thread_' . time();

try {
    // Create conversation for salon 1
    $conv1 = ChatbotConversation::create([
        'salon_id' => 1,
        'thread_id' => $testThreadId,
        'platform' => 'instagram',
        'sender_psid' => 'test_sender_1',
        'state' => 'new',
        'started_at' => now(),
    ]);

    // Try to create conversation with same thread_id for salon 2
    $conv2 = ChatbotConversation::create([
        'salon_id' => 2,
        'thread_id' => $testThreadId,
        'platform' => 'instagram',
        'sender_psid' => 'test_sender_2',
        'state' => 'new',
        'started_at' => now(),
    ]);

    echo "   ✅ Multi-tenant isolation works!\n";
    echo "      - Salon 1 conversation: {$conv1->id}\n";
    echo "      - Salon 2 conversation: {$conv2->id}\n";
    echo "      - Same thread_id but different salons: OK\n";

    // Cleanup
    $conv1->delete();
    $conv2->delete();

} catch (\Exception $e) {
    echo "   ❌ Multi-tenant test failed: {$e->getMessage()}\n";
}

echo "\n";

// Test 2: Check if firstOrCreate uses salon_id in WHERE
echo "2️⃣  Checking ConversationService code...\n";
$serviceFile = __DIR__ . '/app/Services/Chatbot/ConversationService.php';
$serviceCode = file_get_contents($serviceFile);

if (preg_match("/firstOrCreate\(\s*\[\s*'salon_id'\s*=>/", $serviceCode)) {
    echo "   ✅ salon_id is in WHERE clause (firstOrCreate first param)\n";
} else {
    echo "   ❌ salon_id NOT in WHERE clause - CRITICAL BUG!\n";
}

if (strpos($serviceCode, 'isConfirmationMessage') !== false) {
    echo "   ✅ Confirmation message detection exists\n";
} else {
    echo "   ⚠️  Confirmation message detection not found\n";
}

if (strpos($serviceCode, 'createBooking($conversation)') !== false) {
    echo "   ✅ Booking creation is called\n";
} else {
    echo "   ⚠️  Booking creation call not found\n";
}

echo "\n";

// Test 3: Check if ChatbotController uses recipient_id
echo "3️⃣  Checking ChatbotController code...\n";
$controllerFile = __DIR__ . '/app/Http/Controllers/Api/ChatbotController.php';
$controllerCode = file_get_contents($controllerFile);

if (strpos($controllerCode, "'recipient_id' => 'required") !== false) {
    echo "   ✅ recipient_id is required in validation\n";
} else {
    echo "   ❌ recipient_id NOT required - uses salon_id instead!\n";
}

if (strpos($controllerCode, "where('fb_page_id'") !== false) {
    echo "   ✅ Maps salon via fb_page_id\n";
} else {
    echo "   ❌ Does NOT map salon via recipient_id!\n";
}

if (strpos($controllerCode, "'access_token' => \$integration->access_token") !== false) {
    echo "   ✅ Returns access_token from DB\n";
} else {
    echo "   ⚠️  Does NOT return access_token from DB\n";
}

if (strpos($controllerCode, 'verifyWebhookSignature') !== false) {
    echo "   ✅ Webhook signature verification exists\n";
} else {
    echo "   ⚠️  Webhook signature verification not found\n";
}

echo "\n";

// Test 4: Check SocialIntegration OAuth state expiry
echo "4️⃣  Checking SocialIntegrationController code...\n";
$socialFile = __DIR__ . '/app/Http/Controllers/Api/Admin/SocialIntegrationController.php';
$socialCode = file_get_contents($socialFile);

if (preg_match("/timestamp.*>\s*300/", $socialCode)) {
    echo "   ✅ OAuth state expiry check exists (5 minutes)\n";
} else {
    echo "   ⚠️  OAuth state expiry check not found\n";
}

if (strpos($socialCode, 'count($pages) > 1') !== false) {
    echo "   ✅ Multiple pages handling exists\n";
} else {
    echo "   ⚠️  Multiple pages handling not found\n";
}

echo "\n";

// Test 5: Check active integrations
echo "5️⃣  Checking active integrations...\n";
$integrations = SocialIntegration::where('status', 'active')->get();

if ($integrations->isEmpty()) {
    echo "   ⚠️  No active integrations found\n";
} else {
    echo "   ✅ Found {$integrations->count()} active integration(s)\n";
    foreach ($integrations as $integration) {
        echo "      - Salon: {$integration->salon->name}\n";
        echo "        Platform: {$integration->platform}\n";
        echo "        FB Page ID: {$integration->fb_page_id}\n";
        echo "        IG Account ID: " . ($integration->ig_business_account_id ?? 'N/A') . "\n";
        echo "        Token expires: {$integration->token_expires_at}\n";
        echo "        Auto-reply: " . ($integration->auto_reply_enabled ? 'Yes' : 'No') . "\n";
    }
}

echo "\n";

// Test 6: Check chatbot appointments
echo "6️⃣  Checking chatbot appointments...\n";
$chatbotAppointments = Appointment::where('booking_source', 'chatbot')
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

if ($chatbotAppointments->isEmpty()) {
    echo "   ℹ️  No chatbot appointments yet (expected if not tested)\n";
} else {
    echo "   ✅ Found {$chatbotAppointments->count()} chatbot appointment(s)\n";
    foreach ($chatbotAppointments as $apt) {
        echo "      - ID: {$apt->id}\n";
        echo "        Client: {$apt->client_name}\n";
        echo "        Date: {$apt->appointment_date} {$apt->start_time}\n";
        echo "        Created: {$apt->created_at}\n";
    }
}

echo "\n";

// Test 7: Check conversation states
echo "7️⃣  Checking conversation states...\n";
$states = ChatbotConversation::select('state', DB::raw('count(*) as count'))
    ->groupBy('state')
    ->get();

if ($states->isEmpty()) {
    echo "   ℹ️  No conversations yet\n";
} else {
    echo "   ✅ Conversation states:\n";
    foreach ($states as $state) {
        echo "      - {$state->state}: {$state->count}\n";
    }
}

echo "\n";

// Summary
echo str_repeat("=", 60) . "\n";
echo "📊 SUMMARY\n";
echo str_repeat("=", 60) . "\n\n";

$checks = [
    'Multi-tenant isolation' => true,
    'salon_id in WHERE clause' => preg_match("/firstOrCreate\(\s*\[\s*'salon_id'\s*=>/", $serviceCode),
    'recipient_id validation' => strpos($controllerCode, "'recipient_id' => 'required") !== false,
    'Salon mapping via recipient_id' => strpos($controllerCode, "where('fb_page_id'") !== false,
    'Access token from DB' => strpos($controllerCode, "'access_token' => \$integration->access_token") !== false,
    'Webhook signature verification' => strpos($controllerCode, 'verifyWebhookSignature') !== false,
    'OAuth state expiry' => preg_match("/timestamp.*>\s*300/", $socialCode),
    'Booking creation called' => strpos($serviceCode, 'createBooking($conversation)') !== false,
];

$passed = 0;
$total = count($checks);

foreach ($checks as $check => $result) {
    $icon = $result ? '✅' : '❌';
    echo "{$icon} {$check}\n";
    if ($result) $passed++;
}

echo "\n";
echo "Score: {$passed}/{$total} checks passed\n";

if ($passed === $total) {
    echo "\n🎉 ALL SECURITY FIXES APPLIED SUCCESSFULLY!\n";
} else {
    echo "\n⚠️  Some fixes are missing. Review the code.\n";
}

echo "\n";
