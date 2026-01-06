<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=================================================================\n";
echo "FIX APPOINTMENTS.IS_GUEST BOOLEAN CONVERSION\n";
echo "=================================================================\n\n";

try {
    // Check current type
    $result = DB::select("
        SELECT data_type, column_default, is_nullable
        FROM information_schema.columns
        WHERE table_name = 'appointments'
        AND column_name = 'is_guest'
    ");

    echo "Current state:\n";
    echo "  Type: " . $result[0]->data_type . "\n";
    echo "  Default: " . ($result[0]->column_default ?? 'NULL') . "\n";
    echo "  Nullable: " . $result[0]->is_nullable . "\n\n";

    if ($result[0]->data_type === 'boolean') {
        echo "✅ Column is already BOOLEAN type!\n";
        exit(0);
    }

    echo "Converting to BOOLEAN...\n\n";

    // Step 1: Drop CHECK constraint
    echo "Step 1: Dropping CHECK constraint...\n";
    DB::statement('ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_is_guest_check');
    echo "✅ CHECK constraint dropped\n\n";

    // Step 2: Drop default
    echo "Step 2: Dropping default...\n";
    DB::statement('ALTER TABLE appointments ALTER COLUMN is_guest DROP DEFAULT');
    echo "✅ Default dropped\n\n";

    // Step 3: Convert type
    echo "Step 3: Converting type...\n";
    DB::statement('ALTER TABLE appointments ALTER COLUMN is_guest TYPE BOOLEAN USING (is_guest::integer != 0)');
    echo "✅ Type converted\n\n";

    // Step 4: Set default
    echo "Step 4: Setting default...\n";
    DB::statement('ALTER TABLE appointments ALTER COLUMN is_guest SET DEFAULT false');
    echo "✅ Default set\n\n";

    // Step 5: Set NOT NULL
    echo "Step 5: Setting NOT NULL...\n";
    DB::statement('ALTER TABLE appointments ALTER COLUMN is_guest SET NOT NULL');
    echo "✅ NOT NULL set\n\n";

    // Verify
    $result = DB::select("
        SELECT data_type, column_default, is_nullable
        FROM information_schema.columns
        WHERE table_name = 'appointments'
        AND column_name = 'is_guest'
    ");

    echo "=================================================================\n";
    echo "FINAL STATE\n";
    echo "=================================================================\n";
    echo "  Type: " . $result[0]->data_type . "\n";
    echo "  Default: " . ($result[0]->column_default ?? 'NULL') . "\n";
    echo "  Nullable: " . $result[0]->is_nullable . "\n\n";

    if ($result[0]->data_type === 'boolean') {
        echo "✅ SUCCESS! Column is now BOOLEAN type!\n\n";

        // Show sample data
        echo "Sample data:\n";
        $appointments = DB::table('appointments')
            ->select('id', 'is_guest', 'client_name')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        foreach ($appointments as $apt) {
            $isGuestValue = var_export($apt->is_guest, true);
            $isGuestType = gettype($apt->is_guest);
            echo sprintf("  ID: %-5s is_guest: %-10s (type: %-10s) %s\n",
                $apt->id,
                $isGuestValue,
                $isGuestType,
                $apt->client_name
            );
        }

        echo "\n✅ DONE! You can now use true/false directly in code.\n";
    } else {
        echo "❌ FAILED! Column is still " . $result[0]->data_type . "\n";
    }

} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
