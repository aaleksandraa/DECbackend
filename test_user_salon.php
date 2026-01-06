<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Salon;

echo "=== Checking All Salons ===\n\n";

$salons = Salon::all();
echo "Total salons: " . $salons->count() . "\n\n";

foreach ($salons as $salon) {
    echo "Salon ID: {$salon->id}\n";
    echo "  Name: {$salon->name}\n";
    echo "  Slug: {$salon->slug}\n";
    echo "  Owner ID: {$salon->owner_id}\n";
    $owner = User::find($salon->owner_id);
    echo "  Owner: " . ($owner ? $owner->name . " ({$owner->email})" : "Not found") . "\n";
    echo "  Status: {$salon->status}\n\n";
}

echo "=== Checking Salon Owners ===\n\n";

$owners = User::where('role', 'vlasnik_salona')->get();
echo "Total salon owners: " . $owners->count() . "\n\n";

foreach ($owners as $owner) {
    echo "User ID: {$owner->id}\n";
    echo "  Name: {$owner->name}\n";
    echo "  Email: {$owner->email}\n";
    $salon = Salon::where('owner_id', $owner->id)->first();
    echo "  Salon: " . ($salon ? "{$salon->id} - {$salon->name}" : "No salon") . "\n\n";
}

echo "=== Checking for 'Mr Barber' Salon ===\n\n";

$mrBarber = Salon::where('slug', 'frizerski-salon-mr-barber')->first();
if ($mrBarber) {
    echo "Found Mr Barber salon:\n";
    echo "  ID: {$mrBarber->id}\n";
    echo "  Name: {$mrBarber->name}\n";
    echo "  Owner ID: {$mrBarber->owner_id}\n";
    $owner = User::find($mrBarber->owner_id);
    echo "  Owner: " . ($owner ? $owner->name . " ({$owner->email})" : "Not found") . "\n";
} else {
    echo "Mr Barber salon not found!\n";
}
