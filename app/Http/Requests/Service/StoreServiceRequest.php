<?php

namespace App\Http\Requests\Service;

use App\Http\Requests\BaseRequest;

class StoreServiceRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->id === $this->salon->owner_id || $this->user()->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration' => 'required|integer|min:0|max:480',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'category' => 'required|string|max:255',
            'staff_ids' => 'nullable|array',
            'staff_ids.*' => 'exists:staff,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Naziv usluge je obavezan.',
            'name.max' => 'Naziv usluge ne smije biti duži od 255 karaktera.',
            'duration.required' => 'Trajanje usluge je obavezno.',
            'duration.integer' => 'Trajanje mora biti cijeli broj.',
            'duration.min' => 'Trajanje ne može biti negativno. Koristite 0 za dodatke (npr. pranje kose).',
            'duration.max' => 'Trajanje ne može biti duže od 480 minuta (8 sati).',
            'price.required' => 'Cijena je obavezna.',
            'price.numeric' => 'Cijena mora biti broj.',
            'price.min' => 'Cijena ne može biti negativna.',
            'discount_price.numeric' => 'Popust cijena mora biti broj.',
            'discount_price.min' => 'Popust cijena ne može biti negativna.',
            'category.required' => 'Kategorija je obavezna.',
            'category.max' => 'Kategorija ne smije biti duža od 255 karaktera.',
            'staff_ids.array' => 'Zaposleni moraju biti u obliku liste.',
            'staff_ids.*.exists' => 'Izabrani zaposleni ne postoji.',
        ];
    }
}
