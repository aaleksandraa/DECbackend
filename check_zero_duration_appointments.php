<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Zero Duration Appointments ===\n\n";

// Find appointments where end_time = time (zero duration)
$zeroAppointments = \App\Models\Appointment::whereRaw('end_time = time')
    ->with('service')
    ->get();

echo "Appointments with zero duration (end_time = time): {$zeroAppointments->count()}\n\n";

if ($zeroAppointments->count() > 0) {
    echo "⚠️  WARNING: Found appointments with zero duration!\n\n";

    foreach ($zeroAppointments as $apt) {
        echo "ID: {$apt->id}\n";
        echo "  Service: {$apt->service->name} (duration: {$apt->service->duration} min)\n";
        echo "  Date: {$apt->date}\n";
        echo "  Time: {$apt->time} - {$apt->end_time}\n";
        echo "  Status: {$apt->status}\n";
        echo "  Client: {$apt->client_name}\n";

        if ($apt->service->duration > 0) {
            echo "  ❌ ERROR: Service has duration but appointment has zero duration!\n";
        } else {
            echo "  ℹ️  INFO: This is a 0-duration add-on service (expected)\n";
        }

        echo "\n";
    }

    echo "Run 'php fix_zero_duration_appointments.php' to fix these issues.\n";
} else {
    echo "✅ No appointments with zero duration found.\n";
    echo "   All appointments have proper end_time values.\n";
}

echo "\n=== Checking for Potential Double Bookings ===\n\n";

// Find appointments that might cause double booking issues
$potentialIssues = \App\Models\Appointment::select('staff_id', 'date', 'time', \DB::raw('COUNT(*) as count'))
    ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
    ->groupBy('staff_id', 'date', 'time')
    ->having(\DB::raw('COUNT(*)'), '>', 1)
    ->get();

if ($potentialIssues->count() > 0) {
    echo "⚠️  Found {$potentialIssues->count()} time slots with multiple appointments:\n\n";

    foreach ($potentialIssues as $issue) {
        echo "Staff ID: {$issue->staff_id}, Date: {$issue->date}, Time: {$issue->time}\n";
        echo "  Count: {$issue->count} appointments\n";

        // Get the actual appointments
        $appointments = \App\Models\Appointment::where('staff_id', $issue->staff_id)
            ->where('date', $issue->date)
            ->where('time', $issue->time)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->with('service')
            ->get();

        foreach ($appointments as $apt) {
            echo "    - ID {$apt->id}: {$apt->service->name} ({$apt->service->duration} min) [{$apt->time}-{$apt->end_time}]\n";
        }

        // Check if any have 0 duration
        $hasZeroDuration = $appointments->filter(function($apt) {
            return $apt->service->duration == 0;
        })->count() > 0;

        if ($hasZeroDuration) {
            echo "  ℹ️  INFO: This is OK - includes 0-duration add-on services\n";
        } else {
            echo "  ❌ ERROR: Multiple appointments with duration at same time!\n";
        }

        echo "\n";
    }
} else {
    echo "✅ No potential double booking issues found.\n";
}

echo "\n=== Checking Constraint ===\n\n";

$constraint = \DB::select("
    SELECT
        indexname,
        indexdef
    FROM pg_indexes
    WHERE tablename = 'appointments'
    AND indexname LIKE '%double_booking%'
");

if (empty($constraint)) {
    echo "❌ No double booking constraint found!\n";
    echo "   Run this SQL to create it:\n\n";
    echo "   CREATE UNIQUE INDEX appointments_no_double_booking\n";
    echo "   ON appointments (staff_id, date, time)\n";
    echo "   WHERE status IN ('pending', 'confirmed', 'in_progress');\n";
} else {
    echo "✅ Double booking constraint exists:\n";
    foreach ($constraint as $c) {
        echo "   {$c->indexname}\n";
        echo "   {$c->indexdef}\n";
    }
}

echo "\n=== Summary ===\n";
echo "Zero duration appointments: {$zeroAppointments->count()}\n";
echo "Potential double bookings: {$potentialIssues->count()}\n";
echo "\n";
