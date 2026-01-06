<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Appointment;
use App\Mail\AppointmentConfirmationMail;
use Illuminate\Support\Facades\Mail;

echo "=== Testing Widget Email ===\n\n";

// Find the most recent widget booking
$appointment = Appointment::where('booking_source', 'widget')
    ->orderBy('created_at', 'desc')
    ->first();

if (!$appointment) {
    echo "❌ No widget bookings found\n";
    exit(1);
}

echo "Found appointment:\n";
echo "  ID: {$appointment->id}\n";
echo "  Client: {$appointment->client_name}\n";
echo "  Email: {$appointment->client_email}\n";
echo "  Date: {$appointment->date->format('d.m.Y')}\n";
echo "  Time: {$appointment->time}\n";
echo "  Service IDs: " . json_encode($appointment->service_ids) . "\n";
echo "  Service ID: {$appointment->service_id}\n";
echo "\n";

// Load relationships
$appointment->load(['salon', 'staff', 'service']);

// Get services
$services = $appointment->services();
echo "Services:\n";
foreach ($services as $service) {
    echo "  - {$service->name} ({$service->duration} min)\n";
}
echo "\n";

// Check if email is set
if (!$appointment->client_email) {
    echo "❌ No email address for this appointment\n";
    exit(1);
}

// Try to send email
echo "Sending confirmation email to {$appointment->client_email}...\n";

try {
    Mail::to($appointment->client_email)->send(new AppointmentConfirmationMail($appointment));
    echo "✅ Email sent successfully!\n";
    echo "\n";
    echo "Check your email inbox for: {$appointment->client_email}\n";
} catch (\Exception $e) {
    echo "❌ Failed to send email: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== Email Configuration ===\n";
echo "MAIL_MAILER: " . config('mail.default') . "\n";
echo "MAIL_HOST: " . config('mail.mailers.smtp.host') . "\n";
echo "MAIL_PORT: " . config('mail.mailers.smtp.port') . "\n";
echo "MAIL_FROM: " . config('mail.from.address') . "\n";
echo "QUEUE_CONNECTION: " . config('queue.default') . "\n";
