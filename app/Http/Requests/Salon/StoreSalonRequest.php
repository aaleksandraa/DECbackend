<?php

namespace App\Http\Requests\Salon;

use App\Http\Requests\BaseRequest;

class StoreSalonRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->role === 'salon';
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
            'description' => 'required|string',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'phone' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'website' => 'nullable|string|max:255',
            'working_hours' => 'required|array',
            'working_hours.*' => 'array',
            'working_hours.*.open' => 'required_with:working_hours.*|string',
            'working_hours.*.close' => 'required_with:working_hours.*|string',
            'working_hours.*.is_open' => 'required_with:working_hours.*|boolean',
            'location' => 'required|array',
            'location.lat' => 'required|numeric',
            'location.lng' => 'required|numeric',
            'target_audience' => 'nullable|array',
            'target_audience.women' => 'nullable|boolean',
            'target_audience.men' => 'nullable|boolean',
            'target_audience.children' => 'nullable|boolean',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string',
            'social_media' => 'nullable|array',
            'social_media.facebook' => 'nullable|string',
            'social_media.instagram' => 'nullable|string',
            'social_media.twitter' => 'nullable|string',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Naziv salona je obavezan',
            'description.required' => 'Opis salona je obavezan',
            'address.required' => 'Adresa salona je obavezna',
            'city.required' => 'Grad je obavezan',
            'phone.required' => 'Telefon je obavezan',
            'email.required' => 'Email je obavezan',
            'email.email' => 'Email mora biti validan',
            'working_hours.required' => 'Radno vrijeme je obavezno',
            'location.required' => 'Lokacija je obavezna',
        ];
    }
}
