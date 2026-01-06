<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     * Sanitizes all string inputs to prevent XSS attacks.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(
            $this->sanitizeInput($this->all())
        );
    }

    /**
     * Recursively sanitize input data.
     */
    protected function sanitizeInput(array $data): array
    {
        return collect($data)->map(function ($value) {
            if (is_array($value)) {
                return $this->sanitizeInput($value);
            }

            if (is_string($value)) {
                // Strip HTML tags and trim whitespace
                $value = strip_tags($value);
                $value = trim($value);

                // Convert special characters to HTML entities
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);

                // Decode back for storage (we want clean text, not encoded)
                $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');

                return $value;
            }

            return $value;
        })->toArray();
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'required' => 'Polje :attribute je obavezno.',
            'string' => 'Polje :attribute mora biti tekst.',
            'email' => 'Polje :attribute mora biti validna email adresa.',
            'min' => 'Polje :attribute mora imati najmanje :min karaktera.',
            'max' => 'Polje :attribute ne smije imati više od :max karaktera.',
            'unique' => 'Vrijednost polja :attribute već postoji.',
            'confirmed' => 'Potvrda polja :attribute se ne podudara.',
            'numeric' => 'Polje :attribute mora biti broj.',
            'integer' => 'Polje :attribute mora biti cijeli broj.',
            'date' => 'Polje :attribute mora biti validan datum.',
            'array' => 'Polje :attribute mora biti niz.',
            'exists' => 'Odabrana vrijednost za :attribute nije validna.',
            'in' => 'Odabrana vrijednost za :attribute nije validna.',
            'image' => 'Polje :attribute mora biti slika.',
            'mimes' => 'Polje :attribute mora biti tipa: :values.',
            'regex' => 'Format polja :attribute nije validan.',
        ];
    }
}
