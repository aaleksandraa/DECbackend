<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get services - either single service or multiple services
        $services = $this->services();
        $isMultiService = $this->isMultiService();

        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'client_name' => $this->client_name,
            'client_email' => $this->client_email,
            'client_phone' => $this->client_phone,
            'salon_id' => $this->salon_id,
            'staff_id' => $this->staff_id,
            'staff_name' => $this->staff?->name,
            'service_id' => $this->service_id,
            'service_ids' => $this->service_ids,
            'is_multi_service' => $isMultiService,
            'service_name' => $isMultiService
                ? $services->pluck('name')->join(', ')
                : ($this->service?->name ?? $services->first()?->name),
            'services' => $services->map(function($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'duration' => $service->duration,
                    'price' => $service->price,
                ];
            })->toArray(),
            'date' => $this->date->format('d.m.Y'),
            'time' => $this->time,
            'end_time' => $this->end_time,
            'status' => $this->status,
            'notes' => $this->notes,
            'total_price' => $this->total_price,
            'payment_status' => $this->payment_status,
            'salon' => $this->when($this->relationLoaded('salon'), function () {
                return [
                    'id' => $this->salon->id,
                    'slug' => $this->salon->slug,
                    'name' => $this->salon->name,
                    'address' => $this->salon->address,
                    'city' => $this->salon->city,
                    'phone' => $this->salon->phone,
                ];
            }),
            'staff' => $this->when($this->relationLoaded('staff'), function () {
                return [
                    'id' => $this->staff->id,
                    'name' => $this->staff->name,
                    'role' => $this->staff->role,
                ];
            }),
            'service' => $this->when($this->relationLoaded('service') && $this->service, function () {
                return [
                    'id' => $this->service->id,
                    'name' => $this->service->name,
                    'duration' => $this->service->duration,
                    'price' => $this->service->price,
                ];
            }),
            'review' => $this->when($this->relationLoaded('review'), function () {
                return $this->review ? [
                    'id' => $this->review->id,
                    'rating' => $this->review->rating,
                ] : null;
            }),
            'can_be_cancelled' => $this->canBeCancelled(),
            'can_be_rescheduled' => $this->canBeRescheduled(),
            'can_be_reviewed' => $this->canBeReviewed(),
            'can_be_marked_no_show' => $this->canBeMarkedAsNoShow(),
            'has_expired' => $this->hasExpired(),
            'created_at' => $this->created_at->format('d.m.Y H:i'),
            'updated_at' => $this->updated_at->format('d.m.Y H:i'),
        ];
    }
}
