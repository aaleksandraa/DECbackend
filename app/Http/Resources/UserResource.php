<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'avatar' => $this->avatar ? asset('storage/' . $this->avatar) : null,
            'email_verified_at' => $this->email_verified_at,
            'salon' => $this->when($this->relationLoaded('ownedSalon'), function () {
                return $this->ownedSalon ? [
                    'id' => $this->ownedSalon->id,
                    'name' => $this->ownedSalon->name,
                ] : null;
            }),
            'staff_profile' => $this->when($this->relationLoaded('staffProfile'), function () {
                return $this->staffProfile ? [
                    'id' => $this->staffProfile->id,
                    'name' => $this->staffProfile->name,
                    'role' => $this->staffProfile->role,
                    'salon_id' => $this->staffProfile->salon_id,
                    'auto_confirm' => $this->staffProfile->auto_confirm,
                ] : null;
            }),
            'created_at' => $this->created_at->format('d.m.Y'),
            'updated_at' => $this->updated_at->format('d.m.Y'),
        ];
    }
}
