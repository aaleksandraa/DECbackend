<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'city',
        'date_of_birth',
        'gender',
        'role',
        'avatar',
        'email_verified_at',
        'is_guest',
        'created_via',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_guest' => 'boolean', // Keep cast for reading from database
    ];

    /**
     * Get the salon owned by the user.
     */
    public function ownedSalon(): HasOne
    {
        return $this->hasOne(Salon::class, 'owner_id');
    }

    /**
     * Get the staff profile associated with the user.
     */
    public function staffProfile(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    /**
     * Get the appointments for the user.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'client_id');
    }

    /**
     * Get the reviews left by the user.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'client_id');
    }

    /**
     * Get the favorite salons for the user.
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Get the notifications for the user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'recipient_id');
    }

    /**
     * Get the consents for the user.
     */
    public function consents(): HasMany
    {
        return $this->hasMany(UserConsent::class);
    }

    /**
     * Check if user has active consent of given type.
     */
    public function hasConsent(string $consentType): bool
    {
        return UserConsent::hasActiveConsent($this->id, $consentType);
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is salon owner.
     */
    public function isSalonOwner(): bool
    {
        return $this->role === 'salon';
    }

    /**
     * Check if user is staff.
     */
    public function isStaff(): bool
    {
        return $this->role === 'frizer';
    }

    /**
     * Check if user is client.
     */
    public function isClient(): bool
    {
        return $this->role === 'klijent';
    }

    /**
     * Check if user is guest.
     */
    public function isGuest(): bool
    {
        return $this->is_guest === true;
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\VerifyEmail);
    }
}
