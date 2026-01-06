<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=================================================================\n";
echo "PROVJERA BOOLEAN MIGRACIJA\n";
echo "=================================================================\n\n";

// Check if migrations table exists
if (!Schema::hasTable('migrations')) {
    echo "❌ Migrations tabela ne postoji!\n";
    exit(1);
}

// Check which boolean migrations have been run
$booleanMigrations = [
    '2024_12_27_200000_convert_smallint_to_boolean',
    '2024_12_28_000000_convert_smallint_to_boolean_safe',
    '2024_12_28_100000_convert_remaining_smallint_to_boolean',
];

echo "Provjera izvršenih migracija:\n";
echo str_repeat('-', 65) . "\n";

$allRun = true;
foreach ($booleanMigrations as $migration) {
    $exists = DB::table('migrations')
        ->where('migration', $migration)
        ->exists();

    $status = $exists ? '✅ IZVRŠENA' : '❌ NIJE IZVRŠENA';
    echo sprintf("%-55s %s\n", $migration, $status);

    if (!$exists) {
        $allRun = false;
    }
}

echo str_repeat('-', 65) . "\n\n";

// Check actual column types in database
echo "Provjera tipova kolona u bazi:\n";
echo str_repeat('-', 65) . "\n";

$columnsToCheck = [
    'users' => ['is_guest'],
    'appointments' => ['is_guest'],
    'staff' => ['is_active', 'is_public', 'accepts_bookings', 'auto_confirm'],
    'services' => ['is_active'],
    'widget_settings' => ['is_active'],
];

foreach ($columnsToCheck as $table => $columns) {
    if (!Schema::hasTable($table)) {
        echo "⚠️  Tabela '{$table}' ne postoji\n";
        continue;
    }

    foreach ($columns as $column) {
        if (!Schema::hasColumn($table, $column)) {
            echo "⚠️  Kolona '{$table}.{$column}' ne postoji\n";
            continue;
        }

        // Get column type from PostgreSQL
        $result = DB::select("
            SELECT data_type, column_default
            FROM information_schema.columns
            WHERE table_name = ? AND column_name = ?
        ", [$table, $column]);

        if (!empty($result)) {
            $dataType = $result[0]->data_type;
            $default = $result[0]->column_default;

            $isBoolean = $dataType === 'boolean';
            $status = $isBoolean ? '✅ BOOLEAN' : '❌ ' . strtoupper($dataType);

            echo sprintf("%-30s %-15s (default: %s)\n",
                "{$table}.{$column}",
                $status,
                $default ?? 'NULL'
            );
        }
    }
}

echo str_repeat('-', 65) . "\n\n";

// Summary
if ($allRun) {
    echo "✅ SVE BOOLEAN MIGRACIJE SU IZVRŠENE\n\n";

    // Check if columns are actually boolean
    $needsConversion = false;
    foreach ($columnsToCheck as $table => $columns) {
        foreach ($columns as $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $result = DB::select("
                    SELECT data_type
                    FROM information_schema.columns
                    WHERE table_name = ? AND column_name = ?
                ", [$table, $column]);

                if (!empty($result) && $result[0]->data_type !== 'boolean') {
                    $needsConversion = true;
                    break 2;
                }
            }
        }
    }

    if ($needsConversion) {
        echo "⚠️  UPOZORENJE: Migracije su izvršene ali kolone nisu BOOLEAN tip!\n";
        echo "   Možda je došlo do greške pri izvršavanju migracija.\n";
        echo "   Pokrenite: php artisan migrate:rollback --step=3\n";
        echo "   Zatim: php artisan migrate\n\n";
    } else {
        echo "✅ SVE KOLONE SU PRAVILNO KONVERTOVANE U BOOLEAN\n\n";
    }
} else {
    echo "❌ NEKE BOOLEAN MIGRACIJE NISU IZVRŠENE!\n\n";
    echo "Pokrenite:\n";
    echo "  php artisan migrate\n\n";
    echo "Ili na produkciji:\n";
    echo "  cd /var/www/vhosts/frizerino.com/api.frizerino.com\n";
    echo "  php artisan migrate --force\n\n";
}

// Show sample data
echo "Primjer podataka iz appointments tabele:\n";
echo str_repeat('-', 65) . "\n";

$appointments = DB::table('appointments')
    ->select('id', 'is_guest', 'client_name', 'created_at')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

foreach ($appointments as $apt) {
    $isGuestValue = var_export($apt->is_guest, true);
    $isGuestType = gettype($apt->is_guest);
    echo sprintf("ID: %-5s is_guest: %-10s (tip: %-10s) %s\n",
        $apt->id,
        $isGuestValue,
        $isGuestType,
        $apt->client_name
    );
}

echo "\n";
