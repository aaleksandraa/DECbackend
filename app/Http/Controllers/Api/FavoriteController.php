<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalonResource;
use App\Models\Favorite;
use App\Models\Salon;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FavoriteController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of the user's favorite salons.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $favorites = $user->favorites()->with('salon')->get()->pluck('salon');
        
        return SalonResource::collection($favorites);
    }

    /**
     * Add a salon to favorites.
     */
    public function store(Request $request, Salon $salon): JsonResponse
    {
        $user = $request->user();

        // Check if already favorited
        $existing = Favorite::where('user_id', $user->id)
            ->where('salon_id', $salon->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Salon is already in favorites',
            ], 422);
        }

        $favorite = Favorite::create([
            'user_id' => $user->id,
            'salon_id' => $salon->id,
        ]);

        // Send notification
        $this->notificationService->sendFavoriteAddedNotification($user, $salon);

        return response()->json([
            'message' => 'Salon added to favorites',
            'favorite' => $favorite,
        ], 201);
    }

    /**
     * Remove a salon from favorites.
     */
    public function destroy(Request $request, Salon $salon): JsonResponse
    {
        $user = $request->user();

        $deleted = Favorite::where('user_id', $user->id)
            ->where('salon_id', $salon->id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Salon is not in favorites',
            ], 404);
        }

        // Send notification
        $this->notificationService->sendFavoriteRemovedNotification($user, $salon);

        return response()->json([
            'message' => 'Salon removed from favorites',
        ]);
    }

    /**
     * Check if a salon is in the user's favorites.
     */
    public function check(Request $request, Salon $salon): JsonResponse
    {
        $user = $request->user();

        $isFavorite = Favorite::where('user_id', $user->id)
            ->where('salon_id', $salon->id)
            ->exists();

        return response()->json([
            'is_favorite' => $isFavorite,
        ]);
    }
}