<?php

/**
 * Vidi zadnju grešku iz Laravel loga
 *
 * Pokreni na produkciji:
 * cd /var/www/vhosts/frizerino.com/api.frizerino.com
 * php view_latest_error.php
 */

$logFile = __DIR__ . '/storage/logs/laravel.log';

if (!file_exists($logFile)) {
    echo "❌ Log fajl ne postoji: $logFile\n";
    exit(1);
}

echo "=== ZADNJIH 100 LINIJA IZ LOGA ===\n\n";

// Čitaj zadnjih 100 linija
$lines = [];
$file = new SplFileObject($logFile, 'r');
$file->seek(PHP_INT_MAX);
$lastLine = $file->key();
$startLine = max(0, $lastLine - 100);

$file->seek($startLine);
while (!$file->eof()) {
    $lines[] = $file->current();
    $file->next();
}

// Prikaži sve linije
foreach ($lines as $line) {
    echo $line;
}

echo "\n=== KRAJ LOGA ===\n";
