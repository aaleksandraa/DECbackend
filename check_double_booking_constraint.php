<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Double Booking Constraint ===\n\n";

// Check for appointments on 2026-02-26 at 09:00 for staff_id 1
$appointments = \App\Models\Appointment::where('staff_id', 1)
    ->where('date', '2026-02-26')
    ->where('time', '09:00')
    ->get();

echo "Found {$appointments->count()} appointments for staff_id=1, date=2026-02-26, time=09:00:\n\n";

foreach ($appointments as $apt) {
    echo "ID: {$apt->id}\n";
    echo "  Status: {$apt->status}\n";
    echo "  Time: {$apt->time} - {$apt->end_time}\n";
    echo "  Client: {$apt->client_name}\n";
    echo "  Created: {$apt->created_at}\n";
    echo "\n";
}

// Check constraint definition
echo "=== Checking Constraint Definition ===\n\n";

$constraints = DB::select("
    SELECT
        conname as constraint_name,
        pg_get_constraintdef(oid) as definition
    FROM pg_constraint
    WHERE conrelid = 'appointments'::regclass
    AND conname LIKE '%double_booking%'
");

if (empty($constraints)) {
    echo "❌ No 'double_booking' constraint found!\n";
} else {
    foreach ($constraints as $constraint) {
        echo "Constraint: {$constraint->constraint_name}\n";
        echo "Definition: {$constraint->definition}\n\n";
    }
}

// Check for unique indexes
echo "=== Checking Unique Indexes ===\n\n";

$indexes = DB::select("
    SELECT
        indexname,
        indexdef
    FROM pg_indexes
    WHERE tablename = 'appointments'
    AND indexdef LIKE '%UNIQUE%'
    AND indexname LIKE '%double_booking%'
");

if (empty($indexes)) {
    echo "❌ No 'double_booking' unique index found!\n";
} else {
    foreach ($indexes as $index) {
        echo "Index: {$index->indexname}\n";
        echo "Definition: {$index->indexdef}\n\n";
    }
}

echo "=== Recommendation ===\n\n";
echo "If constraint blocks all appointments (including cancelled/completed),\n";
echo "you need to recreate it as a PARTIAL UNIQUE INDEX:\n\n";
echo "DROP INDEX IF EXISTS appointments_no_double_booking;\n";
echo "CREATE UNIQUE INDEX appointments_no_double_booking \n";
echo "ON appointments (staff_id, date, time) \n";
echo "WHERE status IN ('pending', 'confirmed', 'in_progress');\n";
