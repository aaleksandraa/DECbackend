<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    /**
     * Determine if the user can update the service.
     */
    public function update(User $user, Service $service): bool
    {
        // Admin can update any service
        if ($user->role === 'admin') {
            return true;
        }

        // Salon owner can update their services
        if ($user->role === 'salon') {
            $salon = $user->ownedSalon;
            if ($salon && $salon->id === $service->salon_id) {
                return true;
            }
        }

        // Staff can update services from their salon
        if ($user->role === 'frizer') {
            $staff = $user->staffProfile;
            if ($staff && $staff->salon_id === $service->salon_id) {
                return true;
            }
        }

        return false;
    }
}
