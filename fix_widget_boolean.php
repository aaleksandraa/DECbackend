<?php

/**
 * Quick fix for WidgetController boolean queries
 * PostgreSQL BOOLEAN columns don't work with Laravel's where('column', true)
 * because Laravel converts true to 1 in SQL, and PostgreSQL doesn't allow boolean = integer
 */

$file = __DIR__ . '/app/Http/Controllers/Api/WidgetController.php';
$content = file_get_contents($file);

// Replace all where('is_active', true) with whereRaw('is_active = true')
$content = str_replace(
    "->where('is_active', true)",
    "->whereRaw('is_active = true')",
    $content
);

// Also update comments
$content = str_replace(
    "// Use Laravel's boolean cast - works with both SMALLINT and BOOLEAN",
    "// PostgreSQL BOOLEAN columns require explicit boolean comparison",
    $content
);

file_put_contents($file, $content);

echo "✅ Fixed WidgetController.php\n";
echo "Changed: where('is_active', true) → whereRaw('is_active = true')\n";
echo "\nRun: php artisan config:clear && php artisan cache:clear\n";
