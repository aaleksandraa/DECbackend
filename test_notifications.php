<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Notification Test ===\n\n";

// Count total notifications
$total = App\Models\Notification::count();
echo "Total notifications: $total\n";

// Count unread
$unread = App\Models\Notification::whereRaw('is_read = 0')->count();
echo "Unread notifications: $unread\n\n";

// Show all notifications
echo "All notifications:\n";
App\Models\Notification::all()->each(function($n) {
    echo sprintf(
        "ID: %d, Type: %s, Title: %s, is_read: %d, recipient_id: %d\n",
        $n->id,
        $n->type,
        $n->title,
        $n->is_read,
        $n->recipient_id
    );
});

echo "\n=== Test Complete ===\n";
