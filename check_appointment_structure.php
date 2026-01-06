<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== PROVJERA STRUKTURE APPOINTMENT TABELE ===\n\n";

// Provjeri kolone u appointments tabeli
echo "Kolone u 'appointments' tabeli:\n";
$columns = Schema::getColumnListing('appointments');
foreach ($columns as $column) {
    echo "  - {$column}\n";
}

echo "\n";

// Provjeri da li postoji pivot tabela za multiple services
$tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND (table_name LIKE '%appointment%' OR table_name LIKE '%service%')");

echo "Tabele koje sadrže 'appointment' ili 'service':\n";
foreach ($tables as $table) {
    echo "  - {$table->table_name}\n";
}

echo "\n";

// Provjeri jedan termin detaljno
$apt = \App\Models\Appointment::find(14964);

if ($apt) {
    echo "=== TERMIN ID 14964 (hari, 11:00-12:00) ===\n";
    echo "service_id: {$apt->service_id}\n";

    if ($apt->service) {
        echo "Service: {$apt->service->name} ({$apt->service->duration} min)\n";
    }

    echo "\nSva polja termina:\n";
    foreach ($apt->getAttributes() as $key => $value) {
        if (is_string($value) || is_numeric($value)) {
            echo "  {$key}: {$value}\n";
        }
    }
}

echo "\n=== GOTOVO ===\n";
