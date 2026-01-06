<?php

namespace App\Http\Requests\Appointment;

use App\Http\Requests\BaseRequest;

class StoreAppointmentRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->role === 'klijent' || $this->user()->role === 'salon' || $this->user()->role === 'frizer';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'salon_id' => 'required|exists:salons,id',
            'staff_id' => 'required|exists:staff,id',
            // Accept EITHER service_id (single) OR services array (multiple)
            'service_id' => 'required_without:services|exists:services,id',
            'services' => 'required_without:service_id|array|min:1',
            'services.*.id' => 'required_with:services|exists:services,id',
            'date' => 'required|date_format:d.m.Y|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'notes' => 'nullable|string',
        ];

        // For manual booking by salon/frizer, require client info
        if ($this->user()->role === 'salon' || $this->user()->role === 'frizer') {
            $rules['client_name'] = 'required|string|max:255';
            $rules['client_phone'] = 'required|string|max:20';
            $rules['client_email'] = 'nullable|email|max:255';
            $rules['client_address'] = 'nullable|string|max:500';
            $rules['is_manual'] = 'nullable|boolean';
        }

        return $rules;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.after_or_equal' => 'Datum mora biti danas ili u budućnosti',
            'client_name.required' => 'Ime klijenta je obavezno',
            'client_phone.required' => 'Telefon klijenta je obavezan',
        ];
    }
}
