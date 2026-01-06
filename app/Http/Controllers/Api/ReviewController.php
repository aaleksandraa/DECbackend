<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Requests\Review\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Appointment;
use App\Models\Review;
use App\Models\Salon;
use App\Models\Staff;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of the reviews for a salon.
     */
    public function index(Salon $salon, Request $request): AnonymousResourceCollection
    {
        $query = $salon->reviews()->with(['client', 'staff']);

        // Filter by rating
        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        // Filter by staff
        if ($request->has('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }

        // Filter by minimum rating
        if ($request->has('min_rating')) {
            $query->where('rating', '>=', $request->min_rating);
        }

        // Order by
        if ($request->has('order_by')) {
            $orderBy = $request->order_by;
            $direction = $request->order_direction ?? 'desc';

            if ($orderBy === 'date') {
                $query->orderBy('date', $direction);
            } elseif ($orderBy === 'rating') {
                $query->orderBy('rating', $direction);
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $reviews = $query->paginate($request->per_page ?? 15);

        return ReviewResource::collection($reviews);
    }

    /**
     * Store a newly created review in storage.
     */
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $user = $request->user();
        $appointment = Appointment::findOrFail($request->appointment_id);

        // Check if the user is the client of the appointment
        if ($appointment->client_id !== $user->id) {
            return response()->json([
                'message' => 'You can only review your own appointments',
            ], 403);
        }

        // Check if the appointment is completed
        if ($appointment->status !== 'completed') {
            return response()->json([
                'message' => 'You can only review completed appointments',
            ], 422);
        }

        // Check if the appointment has already been reviewed
        if ($appointment->review()->exists()) {
            return response()->json([
                'message' => 'This appointment has already been reviewed',
            ], 422);
        }

        $review = Review::create([
            'client_id' => $user->id,
            'client_name' => $user->name,
            'salon_id' => $appointment->salon_id,
            'staff_id' => $appointment->staff_id,
            'appointment_id' => $appointment->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'date' => now()->format('d.m.Y'),
            'is_verified' => true,
        ]);

        // Update salon and staff ratings
        $salon = Salon::find($appointment->salon_id);
        $salon->calculateRating();

        if ($appointment->staff_id) {
            $staff = Staff::find($appointment->staff_id);
            $staff->calculateRating();
        }

        // Send notifications
        $this->notificationService->sendNewReviewNotifications($review);

        // Invalidate cache
        \App\Services\CacheService::invalidateSalon($salon->id, $salon->slug);

        return response()->json([
            'message' => 'Review created successfully',
            'review' => new ReviewResource($review),
        ], 201);
    }

    /**
     * Display the specified review.
     */
    public function show(Review $review): ReviewResource
    {
        $review->load(['client', 'salon', 'staff', 'appointment']);

        return new ReviewResource($review);
    }

    /**
     * Update the specified review in storage.
     */
    public function update(UpdateReviewRequest $request, Review $review): JsonResponse
    {
        $this->authorize('update', $review);

        $review->update($request->validated());

        // Update salon and staff ratings
        $salon = Salon::find($review->salon_id);
        $salon->calculateRating();

        if ($review->staff_id) {
            $staff = Staff::find($review->staff_id);
            $staff->calculateRating();
        }

        return response()->json([
            'message' => 'Review updated successfully',
            'review' => new ReviewResource($review),
        ]);
    }

    /**
     * Remove the specified review from storage.
     */
    public function destroy(Review $review): JsonResponse
    {
        $this->authorize('delete', $review);

        $review->delete();

        // Update salon and staff ratings
        $salon = Salon::find($review->salon_id);
        $salon->calculateRating();

        if ($review->staff_id) {
            $staff = Staff::find($review->staff_id);
            $staff->calculateRating();
        }

        return response()->json([
            'message' => 'Review deleted successfully',
        ]);
    }

    /**
     * Add a response to a review.
     */
    public function addResponse(Request $request, Review $review): JsonResponse
    {
        $this->authorize('respond', $review);

        $request->validate([
            'response' => 'required|string|max:1000',
        ]);

        $user = $request->user();
        $respondedBy = $user->isSalonOwner() ? $user->ownedSalon->name : $user->name;

        $review->addResponse($request->response, $respondedBy);

        // Send notification to the client
        $this->notificationService->sendReviewResponseNotification($review);

        return response()->json([
            'message' => 'Response added successfully',
            'review' => new ReviewResource($review),
        ]);
    }
}
