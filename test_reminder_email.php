<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Appointment;
use App\Mail\AppointmentReminderMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

echo "=== Test Reminder Email ===\n\n";

// Find an appointment for tomorrow with a client that has email
$tomorrow = Carbon::tomorrow()->format('Y-m-d');

$appointment = Appointment::where('date', $tomorrow)
    ->where('status', 'confirmed')
    ->whereHas('client', function($query) {
        $query->whereNotNull('email');
    })
    ->with(['client', 'salon', 'service', 'staff'])
    ->first();

if (!$appointment) {
    echo "❌ No appointments found for tomorrow with client email\n";
    echo "Tomorrow date: {$tomorrow}\n\n";

    // Show all appointments for tomorrow
    $allAppointments = Appointment::where('date', $tomorrow)
        ->where('status', 'confirmed')
        ->with(['client'])
        ->get();

    echo "Total appointments for tomorrow: " . $allAppointments->count() . "\n";
    foreach ($allAppointments as $app) {
        echo "  - Appointment #{$app->id}: ";
        if ($app->client) {
            echo "Client: {$app->client->name}, Email: " . ($app->client->email ?: 'NO EMAIL') . "\n";
        } else {
            echo "No client (guest booking)\n";
        }
    }
    exit(1);
}

echo "✅ Found appointment for testing:\n";
echo "  ID: {$appointment->id}\n";
echo "  Client: {$appointment->client->name}\n";
echo "  Email: {$appointment->client->email}\n";
echo "  Salon: {$appointment->salon->name}\n";
echo "  Service: {$appointment->service->name}\n";
echo "  Date: {$appointment->date}\n";
echo "  Time: {$appointment->time}\n\n";

echo "Sending test email...\n";

try {
    Mail::to($appointment->client->email)->send(new AppointmentReminderMail($appointment));
    echo "✅ Email sent successfully!\n";
    echo "Check inbox: {$appointment->client->email}\n";
} catch (\Exception $e) {
    echo "❌ Error sending email:\n";
    echo $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
