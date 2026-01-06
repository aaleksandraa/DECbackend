<?php

namespace App\Http\Requests\Service;

use App\Http\Requests\BaseRequest;

class UpdateServiceRequest extends BaseRequest
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
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'duration' => 'sometimes|integer|min:0|max:480',
            'price' => 'sometimes|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'category' => 'sometimes|string|max:255',
            'staff_ids' => 'nullable|array',
            'staff_ids.*' => 'exists:staff,id',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Naziv usluge ne smije biti duži od 255 karaktera.',
            'duration.integer' => 'Trajanje mora biti cijeli broj.',
            'duration.min' => 'Trajanje ne može biti negativno. Koristite 0 za dodatke (npr. pranje kose).',
            'duration.max' => 'Trajanje ne može biti duže od 480 minuta (8 sati).',
            'price.numeric' => 'Cijena mora biti broj.',
            'price.min' => 'Cijena ne može biti negativna.',
            'discount_price.numeric' => 'Popust cijena mora biti broj.',
            'discount_price.min' => 'Popust cijena ne može biti negativna.',
            'category.max' => 'Kategorija ne smije biti duža od 255 karaktera.',
            'staff_ids.array' => 'Zaposleni moraju biti u obliku liste.',
            'staff_ids.*.exists' => 'Izabrani zaposleni ne postoji.',
            'is_active.boolean' => 'Status aktivnosti mora biti true ili false.',
        ];
    }
}
