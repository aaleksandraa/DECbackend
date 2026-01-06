<?php

namespace App\Http\Requests\Salon;

use App\Http\Requests\BaseRequest;

class UpdateSalonRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->id === $this->salon->owner_id || $this->user()->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'address' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'phone' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255',
            'website' => 'nullable|string|max:255',
            'working_hours' => 'sometimes|array',
            'working_hours.*' => 'array',
            'working_hours.*.open' => 'required_with:working_hours.*|string',
            'working_hours.*.close' => 'required_with:working_hours.*|string',
            'working_hours.*.is_open' => 'required_with:working_hours.*|boolean',
            'location' => 'sometimes|array',
            'location.lat' => 'required_with:location|numeric',
            'location.lng' => 'required_with:location|numeric',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'google_maps_url' => 'nullable|string|max:500',
            'target_audience' => 'nullable|array',
            'target_audience.women' => 'nullable|boolean',
            'target_audience.men' => 'nullable|boolean',
            'target_audience.children' => 'nullable|boolean',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string',
            'social_media' => 'nullable|array',
            'social_media.facebook' => 'nullable|string',
            'social_media.instagram' => 'nullable|string',
            'social_media.twitter' => 'nullable|string',
            'auto_confirm' => 'sometimes|boolean',
            'email_notifications_enabled' => 'sometimes|boolean',
            'booking_slot_interval' => 'sometimes|integer|in:15,30,45,60',
            'show_service_gallery' => 'sometimes|boolean',
        ];
    }
}
