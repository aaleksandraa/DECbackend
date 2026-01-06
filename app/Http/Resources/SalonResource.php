<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalonResource extends JsonResource
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
            'slug' => $this->slug,
            'description' => $this->description,
            'address' => $this->address,
            'city' => $this->city,
            'city_slug' => $this->city_slug,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'working_hours' => $this->working_hours,
            'location' => $this->location,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'google_maps_url' => $this->google_maps_url,
            'target_audience' => $this->target_audience,
            'amenities' => $this->amenities,
            'social_media' => $this->social_media,
            'rating' => $this->rating,
            'review_count' => $this->review_count,
            'is_verified' => $this->is_verified,
            'auto_confirm' => $this->auto_confirm,
            'booking_slot_interval' => $this->booking_slot_interval,
            'show_service_gallery' => $this->show_service_gallery,
            'category_order' => $this->category_order,
            'status' => $this->status,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'images' => $this->when($this->relationLoaded('images'), function () {
                return $this->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => $image->url, // Use the model's url accessor
                        'path' => $image->path,
                        'is_primary' => $image->is_primary,
                        'order' => $image->order,
                    ];
                });
            }),
            'services' => $this->when($this->relationLoaded('services'), function () {
                // Sort services by display_order
                $sortedServices = $this->services->sortBy('display_order');
                return ServiceResource::collection($sortedServices);
            }),
            'staff' => $this->when($this->relationLoaded('staff'), function () {
                // Sort staff by display_order
                $sortedStaff = $this->staff->sortBy('display_order');
                return StaffResource::collection($sortedStaff);
            }),
            'reviews' => $this->when($this->relationLoaded('reviews'), function () {
                return ReviewResource::collection($this->reviews);
            }),
            'salon_breaks' => $this->when($this->relationLoaded('salonBreaks'), function () {
                return $this->salonBreaks;
            }),
            'salon_vacations' => $this->when($this->relationLoaded('salonVacations'), function () {
                return $this->salonVacations;
            }),
            'owner' => $this->when($this->relationLoaded('owner'), function () {
                if (!$this->owner) {
                    return null;
                }
                return [
                    'id' => $this->owner->id,
                    'name' => $this->owner->name,
                    'email' => $this->owner->email,
                ];
            }),
            'created_at' => $this->created_at->format('d.m.Y'),
            'updated_at' => $this->updated_at->format('d.m.Y'),
        ];
    }
}
