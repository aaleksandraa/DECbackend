<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Minimum 8 characters
        if (strlen($value) < 8) {
            $fail('Lozinka mora imati najmanje 8 karaktera.');
            return;
        }

        // Maximum 128 characters
        if (strlen($value) > 128) {
            $fail('Lozinka ne smije imati više od 128 karaktera.');
            return;
        }

        // At least one uppercase letter
        if (!preg_match('/[A-Z]/', $value)) {
            $fail('Lozinka mora sadržavati najmanje jedno veliko slovo.');
            return;
        }

        // At least one lowercase letter
        if (!preg_match('/[a-z]/', $value)) {
            $fail('Lozinka mora sadržavati najmanje jedno malo slovo.');
            return;
        }

        // At least one number
        if (!preg_match('/[0-9]/', $value)) {
            $fail('Lozinka mora sadržavati najmanje jedan broj.');
            return;
        }

        // Check for common passwords
        $commonPasswords = [
            'password', 'password123', '123456789', 'qwerty123',
            'admin123', 'letmein', 'welcome', 'monkey123'
        ];

        if (in_array(strtolower($value), $commonPasswords)) {
            $fail('Ova lozinka je previše jednostavna. Izaberite jaču lozinku.');
            return;
        }
    }
}
