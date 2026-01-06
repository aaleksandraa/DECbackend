<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Salon;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics for salon owner.
     *
     * This endpoint is optimized for performance:
     * - Uses database aggregation instead of loading all records
     * - Caches results for 5 minutes
     * - Returns all stats in single response
     */
    public function salonStats(Request $request)
    {
        $user = $request->user();

        if (!$user->isSalonOwner()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $salonId = $user->ownedSalon->id;

        // Cache key unique to this salon
        $cacheKey = "salon_dashboard_stats_{$salonId}";

        // Cache for 5 minutes (300 seconds)
        $stats = Cache::remember($cacheKey, 300, function () use ($salonId) {
            $today = Carbon::today();
            $todayFormatted = $today->format('Y-m-d');

            // Today's appointments count
            $todayCount = Appointment::where('salon_id', $salonId)
                ->whereDate('date', $todayFormatted)
                ->count();

            $todayPendingCount = Appointment::where('salon_id', $salonId)
                ->whereDate('date', $todayFormatted)
                ->where('status', 'pending')
                ->count();

            // This month's appointments count
            $monthStart = $today->copy()->startOfMonth()->format('Y-m-d');
            $monthEnd = $today->copy()->endOfMonth()->format('Y-m-d');

            $monthlyCount = Appointment::where('salon_id', $salonId)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->count();

            // This week's revenue (last 7 days, completed only)
            $weekAgo = $today->copy()->subDays(7)->format('Y-m-d');

            $weeklyRevenue = Appointment::where('salon_id', $salonId)
                ->whereBetween('date', [$weekAgo, $todayFormatted])
                ->where('status', 'completed')
                ->sum('total_price');

            return [
                'today_count' => $todayCount,
                'today_pending_count' => $todayPendingCount,
                'monthly_count' => $monthlyCount,
                'weekly_revenue' => (float) $weeklyRevenue,
            ];
        });

        // Get salon rating (not cached as it changes less frequently)
        $salon = Salon::find($salonId);
        $stats['average_rating'] = $salon->rating ?? 0;
        $stats['review_count'] = $salon->review_count ?? 0;

        return response()->json($stats);
    }

    /**
     * Get today's appointments for salon dashboard.
     * Separate endpoint to avoid loading all appointment data in stats call.
     */
    public function todayAppointments(Request $request)
    {
        $user = $request->user();

        if (!$user->isSalonOwner()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $salonId = $user->ownedSalon->id;
        $today = Carbon::today()->format('Y-m-d');

        $appointments = Appointment::where('salon_id', $salonId)
            ->whereDate('date', $today)
            ->with(['staff:id,name', 'service:id,name'])
            ->orderBy('time', 'asc')
            ->get()
            ->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'time' => $appointment->time,
                    'end_time' => $appointment->end_time,
                    'date' => $appointment->date,
                    'client_id' => $appointment->client_id,
                    'client_name' => $appointment->client_name,
                    'client_phone' => $appointment->client_phone,
                    'client_email' => $appointment->client_email,
                    'status' => $appointment->status,
                    'total_price' => $appointment->total_price,
                    'is_guest' => $appointment->is_guest,
                    'staff' => $appointment->staff ? [
                        'id' => $appointment->staff->id,
                        'name' => $appointment->staff->name,
                    ] : null,
                    'service' => $appointment->service ? [
                        'id' => $appointment->service->id,
                        'name' => $appointment->service->name,
                    ] : null,
                ];
            });

        return response()->json($appointments);
    }

    /**
     * Get dashboard statistics for staff member.
     */
    public function staffStats(Request $request)
    {
        $user = $request->user();

        if (!$user->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $staffId = $user->staffProfile->id;

        // Cache key unique to this staff member
        $cacheKey = "staff_dashboard_stats_{$staffId}";

        // Cache for 5 minutes
        $stats = Cache::remember($cacheKey, 300, function () use ($staffId) {
            $today = Carbon::today();
            $todayFormatted = $today->format('Y-m-d');

            // Today's appointments count
            $todayCount = Appointment::where('staff_id', $staffId)
                ->whereDate('date', $todayFormatted)
                ->count();

            $todayPendingCount = Appointment::where('staff_id', $staffId)
                ->whereDate('date', $todayFormatted)
                ->where('status', 'pending')
                ->count();

            // This month's appointments count
            $monthStart = $today->copy()->startOfMonth()->format('Y-m-d');
            $monthEnd = $today->copy()->endOfMonth()->format('Y-m-d');

            $monthlyCount = Appointment::where('staff_id', $staffId)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->count();

            // This week's revenue (last 7 days, completed only)
            $weekAgo = $today->copy()->subDays(7)->format('Y-m-d');

            $weeklyRevenue = Appointment::where('staff_id', $staffId)
                ->whereBetween('date', [$weekAgo, $todayFormatted])
                ->where('status', 'completed')
                ->sum('total_price');

            return [
                'today_count' => $todayCount,
                'today_pending_count' => $todayPendingCount,
                'monthly_count' => $monthlyCount,
                'weekly_revenue' => (float) $weeklyRevenue,
            ];
        });

        return response()->json($stats);
    }

    /**
     * Get analytics data for salon with caching.
     *
     * This endpoint pre-calculates and caches all analytics data:
     * - Overall salon stats
     * - Per-staff stats
     * - Service performance
     * - Time slot analysis
     *
     * Cache is invalidated when appointments are created/updated/deleted.
     */
    public function salonAnalytics(Request $request)
    {
        $user = $request->user();

        // Allow both salon owners and staff to access analytics
        if (!$user->isSalonOwner() && !$user->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Determine salon ID and staff filter
        if ($user->isSalonOwner()) {
            $salonId = $user->ownedSalon->id;
            $staffId = $request->input('staff_id'); // Optional: filter by staff
        } else {
            // Staff can only see their own analytics
            $salonId = $user->staffProfile->salon_id;
            $staffId = $user->staffProfile->id; // Force filter to their own ID
        }

        $period = $request->input('period', 'this_month'); // this_month, last_month, this_year, last_year, custom
        $startDate = $request->input('start_date'); // For custom period
        $endDate = $request->input('end_date'); // For custom period

        // Build cache key
        $cacheKey = "salon_analytics_{$salonId}_{$period}";
        if ($staffId) {
            $cacheKey .= "_staff_{$staffId}";
        }
        if ($startDate && $endDate) {
            $cacheKey .= "_{$startDate}_{$endDate}";
        }

        // Cache for 10 minutes (600 seconds)
        $analytics = Cache::remember($cacheKey, 600, function () use ($salonId, $period, $staffId, $startDate, $endDate) {
            // Calculate date ranges
            $ranges = $this->getDateRanges($period, $startDate, $endDate);
            $currentRange = $ranges['current'];
            $previousRange = $ranges['previous'];

            // Base query
            $currentQuery = Appointment::where('salon_id', $salonId)
                ->whereBetween('date', [$currentRange['start'], $currentRange['end']]);

            $previousQuery = Appointment::where('salon_id', $salonId)
                ->whereBetween('date', [$previousRange['start'], $previousRange['end']]);

            // Apply staff filter if provided
            if ($staffId) {
                $currentQuery->where('staff_id', $staffId);
                $previousQuery->where('staff_id', $staffId);
            }

            // Current period stats
            $currentTotal = $currentQuery->count();
            $currentCompleted = (clone $currentQuery)->where('status', 'completed')->count();
            $currentRevenue = (clone $currentQuery)->where('status', 'completed')->sum('total_price');
            $currentClients = (clone $currentQuery)->distinct('client_id')->count('client_id');

            // Previous period stats for comparison
            $previousTotal = $previousQuery->count();
            $previousRevenue = (clone $previousQuery)->where('status', 'completed')->sum('total_price');
            $previousClients = (clone $previousQuery)->distinct('client_id')->count('client_id');

            // Top services (with proper joins)
            $topServices = DB::table('appointments')
                ->join('services', 'appointments.service_id', '=', 'services.id')
                ->where('appointments.salon_id', $salonId)
                ->whereBetween('appointments.date', [$currentRange['start'], $currentRange['end']])
                ->when($staffId, function ($query) use ($staffId) {
                    return $query->where('appointments.staff_id', $staffId);
                })
                ->select(
                    'services.id',
                    'services.name',
                    DB::raw('COUNT(*) as bookings'),
                    DB::raw('SUM(CASE WHEN appointments.status = \'completed\' THEN appointments.total_price ELSE 0 END) as revenue')
                )
                ->groupBy('services.id', 'services.name')
                ->orderByDesc('bookings')
                ->limit(5)
                ->get();

            // Top staff (only if not filtering by staff)
            $topStaff = [];
            if (!$staffId) {
                $topStaff = DB::table('appointments')
                    ->join('staff', 'appointments.staff_id', '=', 'staff.id')
                    ->where('appointments.salon_id', $salonId)
                    ->whereBetween('appointments.date', [$currentRange['start'], $currentRange['end']])
                    ->select(
                        'staff.id',
                        'staff.name',
                        'staff.rating',
                        DB::raw('COUNT(*) as bookings'),
                        DB::raw('SUM(CASE WHEN appointments.status = \'completed\' THEN appointments.total_price ELSE 0 END) as revenue')
                    )
                    ->groupBy('staff.id', 'staff.name', 'staff.rating')
                    ->orderByDesc('bookings')
                    ->limit(5)
                    ->get();
            }

            // Time slot analysis (9 AM to 7 PM)
            $timeSlots = [];
            for ($hour = 9; $hour <= 19; $hour++) {
                $count = DB::table('appointments')
                    ->where('salon_id', $salonId)
                    ->whereBetween('date', [$currentRange['start'], $currentRange['end']])
                    ->when($staffId, function ($query) use ($staffId) {
                        return $query->where('staff_id', $staffId);
                    })
                    ->whereRaw("CAST(SUBSTRING(time, 1, 2) AS INTEGER) = ?", [$hour])
                    ->count();

                $timeSlots[] = [
                    'hour' => $hour,
                    'time' => sprintf('%02d:00-%02d:00', $hour, $hour + 1),
                    'bookings' => $count,
                    'percentage' => $currentTotal > 0 ? round(($count / $currentTotal) * 100) : 0
                ];
            }

            return [
                'period' => [
                    'type' => $period,
                    'start' => $currentRange['start'],
                    'end' => $currentRange['end'],
                    'label' => $currentRange['label']
                ],
                'stats' => [
                    'total_appointments' => $currentTotal,
                    'completed_appointments' => $currentCompleted,
                    'total_revenue' => (float) $currentRevenue,
                    'unique_clients' => $currentClients,
                    'completion_rate' => $currentTotal > 0 ? round(($currentCompleted / $currentTotal) * 100) : 0
                ],
                'comparison' => [
                    'appointments_change' => $this->calculatePercentageChange($currentTotal, $previousTotal),
                    'revenue_change' => $this->calculatePercentageChange($currentRevenue, $previousRevenue),
                    'clients_change' => $this->calculatePercentageChange($currentClients, $previousClients)
                ],
                'top_services' => $topServices,
                'top_staff' => $topStaff,
                'time_slots' => $timeSlots
            ];
        });

        return response()->json($analytics);
    }

    /**
     * Get date ranges for analytics periods.
     */
    private function getDateRanges(string $period, ?string $customStart = null, ?string $customEnd = null): array
    {
        $now = Carbon::now();

        switch ($period) {
            case 'this_month':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                $label = 'Ovaj mjesec';
                break;

            case 'last_month':
                $start = $now->copy()->subMonth()->startOfMonth();
                $end = $now->copy()->subMonth()->endOfMonth();
                $label = 'Prošli mjesec';
                break;

            case 'this_year':
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfYear();
                $label = 'Ova godina';
                break;

            case 'last_year':
                $start = $now->copy()->subYear()->startOfYear();
                $end = $now->copy()->subYear()->endOfYear();
                $label = 'Prošla godina';
                break;

            case 'custom':
                if ($customStart && $customEnd) {
                    $start = Carbon::parse($customStart);
                    $end = Carbon::parse($customEnd);
                    $label = 'Prilagođeni period';
                } else {
                    // Fallback to this month
                    $start = $now->copy()->startOfMonth();
                    $end = $now->copy()->endOfMonth();
                    $label = 'Ovaj mjesec';
                }
                break;

            default:
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                $label = 'Ovaj mjesec';
        }

        // Calculate previous period (same duration)
        $duration = $end->diffInDays($start);
        $previousEnd = $start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($duration);

        return [
            'current' => [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'label' => $label
            ],
            'previous' => [
                'start' => $previousStart->format('Y-m-d'),
                'end' => $previousEnd->format('Y-m-d')
            ]
        ];
    }

    /**
     * Calculate percentage change between two values.
     */
    private function calculatePercentageChange($current, $previous): array
    {
        if ($previous == 0) {
            if ($current > 0) {
                return ['value' => 100, 'direction' => 'up'];
            }
            return ['value' => 0, 'direction' => 'neutral'];
        }

        $change = (($current - $previous) / $previous) * 100;

        return [
            'value' => round(abs($change)),
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral')
        ];
    }

    /**
     * Clear dashboard cache (call this when appointments are created/updated/deleted).
     */
    public function clearCache(Request $request)
    {
        $user = $request->user();

        if ($user->isSalonOwner()) {
            $salonId = $user->ownedSalon->id;
            Cache::forget("salon_dashboard_stats_{$salonId}");
        } elseif ($user->isStaff()) {
            $staffId = $user->staffProfile->id;
            Cache::forget("staff_dashboard_stats_{$staffId}");

            // Also clear salon cache if staff belongs to salon
            if ($user->staffProfile->salon_id) {
                Cache::forget("salon_dashboard_stats_{$user->staffProfile->salon_id}");
            }
        }

        return response()->json(['message' => 'Cache cleared']);
    }
}
