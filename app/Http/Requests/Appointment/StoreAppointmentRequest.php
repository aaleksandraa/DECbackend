<?php

namespace App\Http\Requests\Appointment;

use App\Http\Requests\BaseRequest;
use App\Models\Staff;
use Illuminate\Validation\Validator;

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
     * Enforce multi-tenant ownership for manual bookings.
     *
     * Salon owners and staff (frizer) may only create appointments within their
     * own salon. Without this, role checks alone allow booking into ANY salon by
     * passing a foreign salon_id/staff_id (cross-tenant write IDOR). Clients are
     * intentionally allowed to book at any salon.
     */
    public function withValidator(Validator $validator): void
    {
        $user = $this->user();

        if (!$user || !in_array($user->role, ['salon', 'frizer'], true)) {
            return;
        }

        $validator->after(function (Validator $validator) use ($user) {
            $ownSalonId = $user->role === 'salon'
                ? optional($user->ownedSalon)->id
                : optional($user->staffProfile)->salon_id;

            if (!$ownSalonId) {
                $validator->errors()->add('salon_id', 'Nemate povezan salon za zakazivanje.');
                return;
            }

            if ((int) $this->input('salon_id') !== (int) $ownSalonId) {
                $validator->errors()->add('salon_id', 'Ne možete zakazati termin u tuđem salonu.');
                return;
            }

            $staffId = $this->input('staff_id');
            if ($staffId && !Staff::where('id', $staffId)->where('salon_id', $ownSalonId)->exists()) {
                $validator->errors()->add('staff_id', 'Odabrani radnik ne pripada vašem salonu.');
            }
        });
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
