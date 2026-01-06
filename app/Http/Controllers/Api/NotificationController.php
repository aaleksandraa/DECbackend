<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    /**
     * Display a listing of the user's notifications.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        try {
            $user = $request->user();

            if (!$user) {
                return NotificationResource::collection(collect([]));
            }

            $query = $user->notifications();

            // Filter by read status
            if ($request->has('is_read')) {
                $isRead = $request->boolean('is_read');
                $query->where('is_read', $isRead ? 1 : 0);
            }

            // Filter by type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            $notifications = $query->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 15);

            return NotificationResource::collection($notifications);
        } catch (\Exception $e) {
            \Log::error('Error loading notifications: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);

            // Return empty collection instead of error
            return NotificationResource::collection(collect([]));
        }
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Notification $notification): JsonResponse
    {
        $this->authorize('update', $notification);

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        // Update unread notifications (is_read = 0) to read (is_read = 1)
        $user->notifications()->where('is_read', 0)->update(['is_read' => 1]);

        return response()->json([
            'message' => 'All notifications marked as read',
        ]);
    }

    /**
     * Get unread notifications count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                \Log::warning('Unread count requested without user');
                return response()->json([
                    'count' => 0,
                ], 200);
            }

            $count = $user->notifications()->unread()->count();

            \Log::info('Unread count requested', [
                'user_id' => $user->id,
                'count' => $count,
            ]);

            return response()->json([
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting unread count: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);

            // Return 0 instead of error to prevent UI issues
            return response()->json([
                'count' => 0,
            ], 200);
        }
    }

    /**
     * Delete a notification.
     */
    public function destroy(Notification $notification): JsonResponse
    {
        $this->authorize('delete', $notification);

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted',
        ]);
    }
}
