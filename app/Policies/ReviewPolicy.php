<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReviewPolicy
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
    public function view(User $user, Review $review): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === 'klijent';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Review $review): bool
    {
        // Client can update their own reviews
        if ($user->role === 'klijent') {
            return $review->client_id === $user->id;
        }

        // Admin can update any review
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Review $review): bool
    {
        // Client can delete their own reviews
        if ($user->role === 'klijent') {
            return $review->client_id === $user->id;
        }

        // Admin can delete any review
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can respond to the review.
     */
    public function respond(User $user, Review $review): bool
    {
        // Salon owner can respond to reviews for their salon
        if ($user->role === 'salon') {
            $salonId = $user->ownedSalon->id ?? null;
            return $salonId && $review->salon_id === $salonId;
        }

        // Staff can respond to reviews for them
        if ($user->role === 'frizer') {
            $staffId = $user->staffProfile->id ?? null;
            return $staffId && $review->staff_id === $staffId;
        }

        // Admin can respond to any review
        return $user->role === 'admin';
    }
}