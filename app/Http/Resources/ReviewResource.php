<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
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
            'client_id' => $this->client_id,
            'client_name' => $this->client_name,
            'salon_id' => $this->salon_id,
            'staff_id' => $this->staff_id,
            'appointment_id' => $this->appointment_id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'date' => $this->date->format('d.m.Y'),
            'response' => $this->response,
            'is_verified' => $this->is_verified,
            'client' => $this->when($this->relationLoaded('client'), function () {
                return [
                    'id' => $this->client->id,
                    'name' => $this->client->name,
                    'avatar' => $this->client->avatar ? asset('storage/' . $this->client->avatar) : null,
                ];
            }),
            'salon' => $this->when($this->relationLoaded('salon'), function () {
                return [
                    'id' => $this->salon->id,
                    'name' => $this->salon->name,
                ];
            }),
            'staff' => $this->when($this->relationLoaded('staff'), function () {
                return $this->staff ? [
                    'id' => $this->staff->id,
                    'name' => $this->staff->name,
                    'role' => $this->staff->role,
                ] : null;
            }),
            'created_at' => $this->created_at->format('d.m.Y'),
            'updated_at' => $this->updated_at->format('d.m.Y'),
        ];
    }
}