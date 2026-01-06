<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calculate rating dynamically from reviews
        $reviews = $this->reviews()->get();
        $reviewCount = $reviews->count();
        $averageRating = $reviewCount > 0 ? round($reviews->avg('rating'), 1) : 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'role' => $this->role,
            'title' => $this->title,
            'bio' => $this->bio,
            'bio_long' => $this->bio_long,
            'avatar' => $this->avatar ? asset('storage/' . $this->avatar) : null,
            'profile_image' => $this->profile_image ? asset('storage/' . $this->profile_image) : null,
            'years_experience' => $this->years_experience,
            'education' => $this->education,
            'achievements' => $this->achievements,
            'languages' => $this->languages,
            'instagram' => $this->instagram,
            'facebook' => $this->facebook,
            'tiktok' => $this->tiktok,
            'working_hours' => $this->working_hours,
            'specialties' => $this->specialties,
            'rating' => $averageRating,
            'review_count' => $reviewCount,
            'is_active' => $this->is_active,
            'is_public' => $this->is_public,
            'accepts_bookings' => $this->accepts_bookings,
            'booking_note' => $this->booking_note,
            'auto_confirm' => $this->auto_confirm,
            'display_order' => $this->display_order ?? 0,
            'salon_id' => $this->salon_id,
            'salon' => $this->when($this->relationLoaded('salon'), function () {
                return [
                    'id' => $this->salon->id,
                    'name' => $this->salon->name,
                    'slug' => $this->salon->slug,
                    'address' => $this->salon->address,
                    'city' => $this->salon->city,
                    'phone' => $this->salon->phone,
                    'image_url' => $this->salon->image_url,
                    'working_hours' => $this->salon->working_hours,
                    'salon_breaks' => $this->salon->relationLoaded('salonBreaks') ? $this->salon->salonBreaks : [],
                    'salon_vacations' => $this->salon->relationLoaded('salonVacations') ? $this->salon->salonVacations : [],
                ];
            }),
            'services' => $this->when($this->relationLoaded('services'), function () {
                return ServiceResource::collection($this->services);
            }),
            'portfolio' => $this->when($this->relationLoaded('portfolio'), function () {
                return $this->portfolio->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'image_url' => $item->image_url,
                        'title' => $item->title,
                        'description' => $item->description,
                        'category' => $item->category,
                        'tags' => $item->tags,
                        'is_featured' => $item->is_featured,
                    ];
                });
            }),
            'breaks' => $this->when($this->relationLoaded('breaks'), function () {
                return $this->breaks;
            }),
            'vacations' => $this->when($this->relationLoaded('vacations'), function () {
                return $this->vacations;
            }),
            'created_at' => $this->created_at->format('d.m.Y'),
            'updated_at' => $this->updated_at->format('d.m.Y'),
        ];
    }
}
