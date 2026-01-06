<?php

try {
    $pdo = new PDO(
        'pgsql:host=127.0.0.1;port=5432;user=postgres;password=aleksandra'
    );

    // Disable autocommit for CREATE DATABASE
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Drop existing test database if it exists
    $pdo->exec('DROP DATABASE IF EXISTS frizerino_test');

    // Create new test database
    $pdo->exec('CREATE DATABASE frizerino_test');

    echo "✅ Test baza 'frizerino_test' je uspješno kreirana!\n";

} catch (PDOException $e) {
    echo "❌ Greška: " . $e->getMessage() . "\n";
    exit(1);
}
