<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Unread Count Test ===\n\n";

// Get all users
$users = App\Models\User::all();

foreach ($users as $user) {
    echo "User: {$user->name} (ID: {$user->id}, Email: {$user->email})\n";

    // Count total notifications
    $total = $user->notifications()->count();
    echo "  Total notifications: $total\n";

    // Count unread using scope
    $unreadScope = $user->notifications()->unread()->count();
    echo "  Unread (using scope): $unreadScope\n";

    // Count unread using raw query
    $unreadRaw = $user->notifications()->whereRaw('is_read = 0')->count();
    echo "  Unread (using raw): $unreadRaw\n";

    // Show some notifications
    if ($total > 0) {
        echo "  Recent notifications:\n";
        $user->notifications()->latest()->take(3)->get()->each(function($n) {
            echo "    - ID: {$n->id}, Title: {$n->title}, is_read: {$n->is_read}\n";
        });
    }

    echo "\n";
}

echo "=== Test Complete ===\n";
