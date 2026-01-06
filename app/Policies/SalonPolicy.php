<?php

namespace App\Policies;

use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalonPolicy
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
    public function view(User $user, Salon $salon): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === 'salon' || $user->role === 'admin';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Salon $salon): bool
    {
        return $user->id === $salon->owner_id || $user->role === 'admin';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Salon $salon): bool
    {
        return $user->id === $salon->owner_id || $user->role === 'admin';
    }

    /**
     * Determine whether the user can update staff for the salon.
     * This includes salon owners, admins, and the staff member themselves.
     */
    public function updateStaff(User $user, Salon $salon, ?Staff $staff = null): bool
    {
        // Salon owner or admin can update any staff
        if ($user->id === $salon->owner_id || $user->role === 'admin') {
            return true;
        }

        // Staff member can update their own profile/schedule
        if ($staff && $user->staffProfile && $user->staffProfile->id === $staff->id) {
            return true;
        }

        // Check if user is a staff member of this salon updating their own schedule
        if ($user->role === 'frizer' && $user->staffProfile && $user->staffProfile->salon_id === $salon->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update services for the salon.
     */
    public function updateServices(User $user, Salon $salon): bool
    {
        return $user->id === $salon->owner_id || $user->role === 'admin';
    }

    /**
     * Determine whether the user can update schedule for the salon.
     */
    public function updateSchedule(User $user, Salon $salon): bool
    {
        return $user->id === $salon->owner_id || $user->role === 'admin';
    }
}
