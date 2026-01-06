<?php

/**
 * Test script for password validation
 * Run: php test_password_validation.php
 */

require __DIR__ . '/vendor/autoload.php';

use App\Rules\StrongPassword;

// Test passwords
$testPasswords = [
    // Should FAIL
    'short' => 'Test12',           // Too short (6 chars)
    'no_upper' => 'test1234',      // No uppercase
    'no_lower' => 'TEST1234',      // No lowercase
    'no_number' => 'TestTest',     // No number
    'common1' => 'Password123',    // Common password
    'common2' => 'Admin123',       // Common password
    'common3' => 'Qwerty123',      // Common password

    // Should PASS
    'valid1' => 'Aleksandra2025',  // Valid strong password
    'valid2' => 'Test1234',        // Valid strong password
    'valid3' => 'MyPass123',       // Valid strong password
];

echo "=== Testing Password Validation ===\n\n";

$rule = new StrongPassword();

foreach ($testPasswords as $name => $password) {
    echo "Testing: $name = '$password'\n";

    $errors = [];
    $fail = function($message) use (&$errors) {
        $errors[] = $message;
    };

    $rule->validate('password', $password, $fail);

    if (empty($errors)) {
        echo "  ✅ PASS\n";
    } else {
        echo "  ❌ FAIL: " . implode(', ', $errors) . "\n";
    }
    echo "\n";
}

echo "=== Test Complete ===\n";
