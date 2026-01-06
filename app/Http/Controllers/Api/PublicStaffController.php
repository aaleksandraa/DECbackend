<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaffResource;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicStaffController extends Controller
{
    /**
     * Get public staff profile by slug
     */
    public function show(string $slug): JsonResponse
    {
        $staff = Staff::where('slug', $slug)
            ->whereRaw('is_public = true')
            ->whereRaw('is_active = true')
            ->with([
                'salon.salonBreaks' => function ($query) {
                    $query->whereRaw('is_active = true');
                },
                'salon.salonVacations' => function ($query) {
                    $query->whereRaw('is_active = true');
                },
                'breaks' => function ($query) {
                    $query->whereRaw('is_active = true');
                },
                'vacations' => function ($query) {
                    $query->whereRaw('is_active = true');
                },
                'services.images' => function ($query) {
                    $query->orderBy('order');
                },
                'services',
                'portfolio',
                'reviews' => function ($query) {
                    $query->whereRaw('is_verified = true')->latest()->take(10);
                }
            ])
            ->firstOrFail();

        return response()->json([
            'staff' => new StaffResource($staff),
        ]);
    }

    /**
     * Get staff portfolio
     */
    public function portfolio(string $slug): JsonResponse
    {
        $staff = Staff::where('slug', $slug)
            ->whereRaw('is_public = true')
            ->whereRaw('is_active = true')
            ->firstOrFail();

        $portfolio = $staff->portfolio()->orderBy('order')->get();

        return response()->json([
            'portfolio' => $portfolio->map(function ($item) {
                return [
                    'id' => $item->id,
                    'image_url' => $item->image_url,
                    'title' => $item->title,
                    'description' => $item->description,
                    'category' => $item->category,
                    'tags' => $item->tags,
                    'is_featured' => $item->is_featured,
                ];
            }),
        ]);
    }

    /**
     * Get staff reviews
     */
    public function reviews(string $slug, Request $request): JsonResponse
    {
        $staff = Staff::where('slug', $slug)
            ->whereRaw('is_public = true')
            ->whereRaw('is_active = true')
            ->firstOrFail();

        $reviews = $staff->reviews()
            ->whereRaw('is_verified = true')
            ->with('client:id,name')
            ->latest()
            ->paginate($request->per_page ?? 10);

        return response()->json([
            'reviews' => $reviews->items(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Get staff availability for booking
     */
    public function availability(string $slug, Request $request): JsonResponse
    {
        $staff = Staff::where('slug', $slug)
            ->whereRaw('is_public = true')
            ->whereRaw('is_active = true')
            ->whereRaw('accepts_bookings = true')
            ->with(['salon', 'breaks', 'vacations'])
            ->firstOrFail();

        $date = $request->date ?? now()->format('Y-m-d');
        $duration = $request->duration ?? 60;

        // Get available time slots for the day
        $slots = [];
        $workingHours = $staff->working_hours[strtolower(date('l', strtotime($date)))] ?? null;

        if ($workingHours && $workingHours['is_working']) {
            $startTime = strtotime($workingHours['start']);
            $endTime = strtotime($workingHours['end']);
            $slotInterval = $staff->salon->booking_slot_interval ?? 30;

            $currentTime = $startTime;
            while ($currentTime < $endTime) {
                $timeStr = date('H:i', $currentTime);
                $isAvailable = $staff->isAvailable($date, $timeStr, $duration);

                $slots[] = [
                    'time' => $timeStr,
                    'available' => $isAvailable,
                ];

                $currentTime = strtotime("+{$slotInterval} minutes", $currentTime);
            }
        }

        return response()->json([
            'date' => $date,
            'slots' => $slots,
            'working_hours' => $workingHours,
        ]);
    }
}
