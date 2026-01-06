<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AppointmentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Appointment $appointment): bool
    {
        // Client can view their own appointments
        if ($user->role === 'klijent') {
            return $appointment->client_id === $user->id;
        }

        // Staff can view appointments assigned to them
        if ($user->role === 'frizer') {
            $staffId = $user->staffProfile->id ?? null;
            return $staffId && $appointment->staff_id === $staffId;
        }

        // Salon owner can view appointments for their salon
        if ($user->role === 'salon') {
            $salonId = $user->ownedSalon->id ?? null;
            return $salonId && $appointment->salon_id === $salonId;
        }

        // Admin can view any appointment
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Appointment $appointment): bool
    {
        // Client can update their own appointments if they're not completed or cancelled
        if ($user->role === 'klijent') {
            return $appointment->client_id === $user->id && 
                   in_array($appointment->status, ['pending', 'confirmed']);
        }

        // Staff can update appointments assigned to them
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
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Appointment $appointment): bool
    {
        // Only admin can delete appointments
        if ($user->role === 'admin') {
            return true;
        }

        // Salon owner can delete appointments for their salon
        if ($user->role === 'salon') {
            $salonId = $user->ownedSalon->id ?? null;
            return $salonId && $appointment->salon_id === $salonId;
        }

        return false;
    }

    /**
     * Determine whether the user can cancel the appointment.
     */
    public function cancel(User $user, Appointment $appointment): bool
    {
        // Client can cancel their own appointments if they're not completed or cancelled
        if ($user->role === 'klijent') {
            return $appointment->client_id === $user->id && 
                   in_array($appointment->status, ['pending', 'confirmed']);
        }

        // Staff can cancel appointments assigned to them
        if ($user->role === 'frizer') {
            $staffId = $user->staffProfile->id ?? null;
            return $staffId && $appointment->staff_id === $staffId;
        }

        // Salon owner can cancel appointments for their salon
        if ($user->role === 'salon') {
            $salonId = $user->ownedSalon->id ?? null;
            return $salonId && $appointment->salon_id === $salonId;
        }

        // Admin can cancel any appointment
        return $user->role === 'admin';
    }
}