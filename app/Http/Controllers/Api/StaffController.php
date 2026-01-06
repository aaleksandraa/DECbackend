<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Http\Resources\StaffResource;
use App\Http\Resources\AppointmentResource;
use App\Models\Salon;
use App\Models\Staff;
use App\Services\StaffService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class StaffController extends Controller
{
    protected $staffService;

    public function __construct(StaffService $staffService)
    {
        $this->staffService = $staffService;
    }

    /**
     * Display a listing of the staff for a salon.
     */
    public function index(Salon $salon): AnonymousResourceCollection
    {
        $staff = $salon->staff()->with(['services', 'breaks', 'vacations'])->get();

        return StaffResource::collection($staff);
    }

    /**
     * Store a newly created staff in storage.
     */
    public function store(StoreStaffRequest $request, Salon $salon): JsonResponse
    {
        $this->authorize('update', $salon);

        $staff = $this->staffService->createStaff($salon, $request->validated());

        return response()->json([
            'message' => 'Staff created successfully',
            'staff' => new StaffResource($staff),
        ], 201);
    }

    /**
     * Display the specified staff.
     */
    public function show(Salon $salon, Staff $staff): StaffResource
    {
        if ($staff->salon_id !== $salon->id) {
            abort(404);
        }

        $staff->load(['services', 'breaks', 'vacations']);

        return new StaffResource($staff);
    }

    /**
     * Update the specified staff in storage.
     */
    public function update(UpdateStaffRequest $request, Salon $salon, Staff $staff): JsonResponse
    {
        $this->authorize('update', $salon);

        if ($staff->salon_id !== $salon->id) {
            abort(404);
        }

        $staff = $this->staffService->updateStaff($staff, $request->validated());

        return response()->json([
            'message' => 'Staff updated successfully',
            'staff' => new StaffResource($staff),
        ]);
    }

    /**
     * Remove the specified staff from storage.
     */
    public function destroy(Salon $salon, Staff $staff): JsonResponse
    {
        $this->authorize('update', $salon);

        if ($staff->salon_id !== $salon->id) {
            abort(404);
        }

        $staff->delete();

        return response()->json([
            'message' => 'Staff deleted successfully',
        ]);
    }

    /**
     * Upload avatar for the staff.
     * Staff members can upload their own avatar, salon owners can upload any staff avatar.
     */
    public function uploadAvatar(Request $request, Salon $salon, Staff $staff): JsonResponse
    {
        $user = $request->user();

        // Check if user is the staff member themselves or the salon owner
        $isOwnProfile = $user->id === $staff->user_id;
        $isSalonOwner = $user->id === $salon->owner_id;

        if (!$isOwnProfile && !$isSalonOwner) {
            abort(403, 'This action is unauthorized.');
        }

        if ($staff->salon_id !== $salon->id) {
            abort(404);
        }

        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($staff->avatar) {
            Storage::disk('public')->delete($staff->avatar);
        }

        $path = $request->file('avatar')->store('staff', 'public');
        $staff->update(['avatar' => $path]);

        return response()->json([
            'message' => 'Avatar uploaded successfully',
            'avatar_url' => asset('storage/' . $path),
        ]);
    }

    /**
     * Get the staff's schedule.
     */
    public function schedule(Salon $salon, Staff $staff): JsonResponse
    {
        if ($staff->salon_id !== $salon->id) {
            abort(404);
        }

        $breaks = $staff->breaks()->get();
        $vacations = $staff->vacations()->get();

        return response()->json([
            'breaks' => $breaks,
            'vacations' => $vacations,
        ]);
    }

    /**
     * Get the staff's appointments.
     */
    public function appointments(Request $request, Salon $salon, Staff $staff): JsonResponse
    {
        if ($staff->salon_id !== $salon->id) {
            abort(404);
        }

        $request->validate([
            'date' => 'sometimes|string',
            'status' => 'sometimes|in:pending,confirmed,in_progress,completed,cancelled,no_show',
        ]);

        $query = $staff->appointments()->with(['service', 'client']);

        if ($request->has('date')) {
            // Convert European date format (DD.MM.YYYY) to ISO format for database query
            $dateInput = $request->date;
            if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $dateInput)) {
                $dateInput = Carbon::createFromFormat('d.m.Y', $dateInput)->format('Y-m-d');
            }
            $query->whereDate('date', $dateInput);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $appointments = $query->orderBy('date', 'desc')->orderBy('time', 'desc')->get();

        return response()->json([
            'appointments' => AppointmentResource::collection($appointments),
        ]);
    }

    /**
     * Reorder staff members.
     */
    public function reorder(Request $request, Salon $salon): JsonResponse
    {
        $this->authorize('update', $salon);

        $request->validate([
            'staff' => 'required|array',
            'staff.*.id' => 'required|integer|exists:staff,id',
            'staff.*.display_order' => 'required|integer|min:0',
        ]);

        foreach ($request->staff as $item) {
            Staff::where('id', $item['id'])
                ->where('salon_id', $salon->id)
                ->update(['display_order' => $item['display_order']]);
        }

        // Invalidate cache
        \App\Services\CacheService::invalidateSalon($salon->id, $salon->slug);

        return response()->json([
            'message' => 'Staff reordered successfully',
        ]);
    }

    /**
     * Update the authenticated frizer's own settings.
     */
    public function updateOwnSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'frizer') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $staff = Staff::where('user_id', $user->id)->first();

        if (!$staff) {
            return response()->json(['message' => 'Staff profile not found'], 404);
        }

        $validated = $request->validate([
            'auto_confirm' => 'sometimes|boolean',
            'bio' => 'sometimes|nullable|string|max:500',
            'bio_long' => 'sometimes|nullable|string|max:2000',
            'title' => 'sometimes|nullable|string|max:255',
            'years_experience' => 'sometimes|nullable|integer|min:0|max:50',
            'languages' => 'sometimes|nullable|array',
            'languages.*' => 'string|max:100',
            'specialties' => 'sometimes|nullable|array',
            'specialties.*' => 'string|max:100',
            'education' => 'sometimes|nullable|array',
            'education.*.school' => 'required_with:education|string|max:255',
            'education.*.degree' => 'required_with:education|string|max:255',
            'education.*.year' => 'required_with:education|string|max:50',
            'achievements' => 'sometimes|nullable|array',
            'achievements.*.title' => 'required_with:achievements|string|max:255',
            'achievements.*.description' => 'required_with:achievements|string|max:1000',
            'achievements.*.year' => 'required_with:achievements|string|max:50',
            'instagram' => 'sometimes|nullable|url|max:255',
            'facebook' => 'sometimes|nullable|url|max:255',
            'tiktok' => 'sometimes|nullable|url|max:255',
            'accepts_bookings' => 'sometimes|boolean',
            'booking_note' => 'sometimes|nullable|string|max:500',
            'is_public' => 'sometimes|boolean',
        ]);

        $staff->update($validated);

        return response()->json([
            'message' => 'Settings updated successfully',
            'staff' => new StaffResource($staff->fresh()),
        ]);
    }
}
