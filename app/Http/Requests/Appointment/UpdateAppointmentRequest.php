<?php

namespace App\Http\Requests\Appointment;

use App\Http\Requests\BaseRequest;

class UpdateAppointmentRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $appointment = $this->route('appointment');
        $user = $this->user();

        // Client can only update their own appointments
        if ($user->role === 'klijent') {
            return $appointment->client_id === $user->id;
        }

        // Staff can only update appointments assigned to them
        if ($user->role === 'frizer') {
            $staffId = $user->staffProfile->id ?? null;
            return $staffId && $appointment->staff_id === $staffId;
        }

        // Salon owner can update appointments for their salon
        if ($user->role === 'salon') {
            $salonId = $user->ownedSalon->id ?? null;
            return $salonId && $appointment->salon_id === $salonId;
        }

        // Admin can update any appointment
        return $user->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'notes' => 'nullable|string',
        ];

        // Only salon owner, staff, or admin can change these fields
        if (in_array($this->user()->role, ['salon', 'frizer', 'admin'])) {
            $rules = array_merge($rules, [
                'status' => 'sometimes|string|in:pending,confirmed,in_progress,completed,cancelled,no_show',
                'payment_status' => 'sometimes|string|in:pending,paid,refunded',
            ]);
        }

        // Only client or admin can change these fields
        if (in_array($this->user()->role, ['klijent', 'admin'])) {
            $rules = array_merge($rules, [
                'date' => 'sometimes|date_format:d.m.Y|after_or_equal:today',
                'time' => 'sometimes|date_format:H:i',
                'staff_id' => 'sometimes|exists:staff,id',
                'service_id' => 'sometimes|exists:services,id',
            ]);
        }

        return $rules;
    }
}
