<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Staff;
use App\Services\CalendarFeedService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarFeedController extends Controller
{
    /**
     * Return import links for authenticated staff member.
     */
    public function myLinks(Request $request, CalendarFeedService $calendarFeedService): JsonResponse
    {
        $user = $request->user();

        if (!$user || !$user->isStaff()) {
            return response()->json([
                'message' => 'Calendar sync is available only for staff accounts.',
            ], 403);
        }

        $staff = Staff::with('salon')->where('user_id', $user->id)->first();

        if (!$staff) {
            return response()->json([
                'message' => 'Staff profile not found.',
            ], 404);
        }

        return response()->json([
            'staff_id' => $staff->id,
            'staff_name' => $staff->name,
            'calendar' => $calendarFeedService->getImportLinks($staff),
        ]);
    }

    /**
     * Regenerate import token for authenticated staff member.
     */
    public function regenerate(Request $request, CalendarFeedService $calendarFeedService): JsonResponse
    {
        $user = $request->user();

        if (!$user || !$user->isStaff()) {
            return response()->json([
                'message' => 'Calendar sync is available only for staff accounts.',
            ], 403);
        }

        $staff = Staff::where('user_id', $user->id)->first();

        if (!$staff) {
            return response()->json([
                'message' => 'Staff profile not found.',
            ], 404);
        }

        $calendarFeedService->regenerateFeedToken($staff);
        $staff->refresh();

        return response()->json([
            'message' => 'Calendar link regenerated successfully.',
            'calendar' => $calendarFeedService->getImportLinks($staff),
        ]);
    }

    /**
     * Public ICS feed for staff calendar subscriptions.
     */
    public function staffFeed(string $token, CalendarFeedService $calendarFeedService)
    {
        $staff = Staff::with('salon')
            ->where('calendar_feed_token', $token)
            ->where('is_active', true)
            ->first();

        if (!$staff) {
            return response()->json([
                'message' => 'Calendar feed not found.',
            ], 404);
        }

        $from = Carbon::now('Europe/Sarajevo')->subMonths(3)->startOfDay()->toDateString();
        $to = Carbon::now('Europe/Sarajevo')->addMonths(12)->endOfDay()->toDateString();

        $appointments = Appointment::with(['salon', 'service'])
            ->where('staff_id', $staff->id)
            ->whereBetween('date', [$from, $to])
            ->whereIn('status', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'])
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        $icsContent = $calendarFeedService->generateStaffFeedIcs($staff, $appointments);

        return response($icsContent, 200)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename="frizerino-staff-' . $staff->id . '.ics"')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }
}
