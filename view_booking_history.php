<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "=== Booking History Viewer ===\n\n";

// Get command line arguments
$filter = $argv[1] ?? 'recent';
$limit = isset($argv[2]) ? (int)$argv[2] : 50;

echo "Filter: {$filter}\n";
echo "Limit: {$limit}\n";
echo str_repeat('=', 100) . "\n\n";

try {
    switch ($filter) {
        case 'recent':
            showRecentBookings($limit);
            break;

        case 'today':
            showTodayBookings();
            break;

        case 'failed':
            showFailedBookings($limit);
            break;

        case 'pending':
            showPendingBookings();
            break;

        case 'stats':
            showBookingStats();
            break;

        case 'by-salon':
            $salonId = $argv[2] ?? null;
            if (!$salonId) {
                echo "❌ Please provide salon ID: php view_booking_history.php by-salon SALON_ID\n";
                exit(1);
            }
            showBookingsBySalon($salonId, $argv[3] ?? 50);
            break;

        case 'by-date':
            $date = $argv[2] ?? date('Y-m-d');
            showBookingsByDate($date);
            break;

        default:
            showUsage();
            exit(0);
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== End of Report ===\n";

/**
 * Show recent bookings
 */
function showRecentBookings(int $limit)
{
    echo "📋 Recent Bookings (Last {$limit}):\n\n";

    $appointments = DB::table('appointments')
        ->join('salons', 'appointments.salon_id', '=', 'salons.id')
        ->join('staff', 'appointments.staff_id', '=', 'staff.id')
        ->join('services', 'appointments.service_id', '=', 'services.id')
        ->leftJoin('users', 'appointments.client_id', '=', 'users.id')
        ->select(
            'appointments.id',
            'appointments.date',
            'appointments.time',
            'appointments.status',
            'appointments.created_at',
            'salons.name as salon_name',
            'staff.name as staff_name',
            'services.name as service_name',
            DB::raw('COALESCE(users.name, appointments.client_name) as client_name'),
            DB::raw('COALESCE(users.email, appointments.client_email) as client_email'),
            'appointments.is_guest'
        )
        ->orderBy('appointments.created_at', 'desc')
        ->limit($limit)
        ->get();

    if ($appointments->isEmpty()) {
        echo "No bookings found.\n";
        return;
    }

    foreach ($appointments as $apt) {
        $statusIcon = getStatusIcon($apt->status);
        $guestLabel = $apt->is_guest ? ' (Guest)' : '';

        echo "{$statusIcon} #{$apt->id} - {$apt->client_name}{$guestLabel}\n";
        echo "   Salon: {$apt->salon_name}\n";
        echo "   Service: {$apt->service_name} with {$apt->staff_name}\n";
        echo "   Date: {$apt->date} at {$apt->time}\n";
        echo "   Status: {$apt->status}\n";
        echo "   Booked: {$apt->created_at}\n";
        if ($apt->client_email) {
            echo "   Email: {$apt->client_email}\n";
        }
        echo "\n";
    }

    echo "Total: " . $appointments->count() . " bookings\n";
}

/**
 * Show today's bookings
 */
function showTodayBookings()
{
    $today = date('Y-m-d');
    echo "📅 Today's Bookings ({$today}):\n\n";

    $appointments = DB::table('appointments')
        ->join('salons', 'appointments.salon_id', '=', 'salons.id')
        ->join('staff', 'appointments.staff_id', '=', 'staff.id')
        ->join('services', 'appointments.service_id', '=', 'services.id')
        ->leftJoin('users', 'appointments.client_id', '=', 'users.id')
        ->select(
            'appointments.*',
            'salons.name as salon_name',
            'staff.name as staff_name',
            'services.name as service_name',
            DB::raw('COALESCE(users.name, appointments.client_name) as client_name')
        )
        ->whereDate('appointments.date', $today)
        ->orderBy('appointments.time')
        ->get();

    if ($appointments->isEmpty()) {
        echo "No bookings for today.\n";
        return;
    }

    $byStatus = $appointments->groupBy('status');

    foreach ($byStatus as $status => $group) {
        echo "\n{$status} ({$group->count()}):\n";
        foreach ($group as $apt) {
            echo "  {$apt->time} - {$apt->client_name} @ {$apt->salon_name} ({$apt->service_name})\n";
        }
    }

    echo "\nTotal: " . $appointments->count() . " bookings\n";
}

/**
 * Show failed bookings (from failed_jobs table)
 */
function showFailedBookings(int $limit)
{
    echo "❌ Failed Booking Jobs (Last {$limit}):\n\n";

    $failedJobs = DB::table('failed_jobs')
        ->where('payload', 'like', '%Appointment%')
        ->orderBy('failed_at', 'desc')
        ->limit($limit)
        ->get();

    if ($failedJobs->isEmpty()) {
        echo "No failed booking jobs found. ✅\n";
        return;
    }

    foreach ($failedJobs as $job) {
        echo "ID: {$job->id}\n";
        echo "Failed at: {$job->failed_at}\n";
        echo "Exception: " . substr($job->exception, 0, 200) . "...\n";
        echo "\n";
    }

    echo "Total: " . $failedJobs->count() . " failed jobs\n";
}

/**
 * Show pending bookings
 */
function showPendingBookings()
{
    echo "⏳ Pending Bookings:\n\n";

    $appointments = DB::table('appointments')
        ->join('salons', 'appointments.salon_id', '=', 'salons.id')
        ->join('staff', 'appointments.staff_id', '=', 'staff.id')
        ->leftJoin('users', 'appointments.client_id', '=', 'users.id')
        ->select(
            'appointments.*',
            'salons.name as salon_name',
            'staff.name as staff_name',
            DB::raw('COALESCE(users.name, appointments.client_name) as client_name')
        )
        ->where('appointments.status', 'pending')
        ->orderBy('appointments.date')
        ->orderBy('appointments.time')
        ->get();

    if ($appointments->isEmpty()) {
        echo "No pending bookings. ✅\n";
        return;
    }

    foreach ($appointments as $apt) {
        echo "#{$apt->id} - {$apt->client_name}\n";
        echo "   {$apt->salon_name} - {$apt->staff_name}\n";
        echo "   {$apt->date} at {$apt->time}\n";
        echo "   Created: {$apt->created_at}\n";
        echo "\n";
    }

    echo "Total: " . $appointments->count() . " pending bookings\n";
}

/**
 * Show booking statistics
 */
function showBookingStats()
{
    echo "📊 Booking Statistics:\n\n";

    // Total bookings
    $total = DB::table('appointments')->count();
    echo "Total Bookings: {$total}\n\n";

    // By status
    echo "By Status:\n";
    $byStatus = DB::table('appointments')
        ->select('status', DB::raw('count(*) as count'))
        ->groupBy('status')
        ->get();

    foreach ($byStatus as $stat) {
        $icon = getStatusIcon($stat->status);
        $percentage = $total > 0 ? round(($stat->count / $total) * 100, 1) : 0;
        echo "  {$icon} {$stat->status}: {$stat->count} ({$percentage}%)\n";
    }

    // Today's bookings
    $today = date('Y-m-d');
    $todayCount = DB::table('appointments')
        ->whereDate('date', $today)
        ->count();
    echo "\nToday's Bookings: {$todayCount}\n";

    // This week
    $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
    $weekEnd = Carbon::now()->endOfWeek()->format('Y-m-d');
    $weekCount = DB::table('appointments')
        ->whereBetween('date', [$weekStart, $weekEnd])
        ->count();
    echo "This Week: {$weekCount}\n";

    // This month
    $monthStart = Carbon::now()->startOfMonth()->format('Y-m-d');
    $monthEnd = Carbon::now()->endOfMonth()->format('Y-m-d');
    $monthCount = DB::table('appointments')
        ->whereBetween('date', [$monthStart, $monthEnd])
        ->count();
    echo "This Month: {$monthCount}\n";

    // Guest vs Registered
    echo "\nBy Client Type:\n";
    $guestCount = DB::table('appointments')->where('is_guest', true)->count();
    $registeredCount = DB::table('appointments')->where('is_guest', false)->count();
    echo "  Guest Bookings: {$guestCount}\n";
    echo "  Registered Users: {$registeredCount}\n";

    // Top salons
    echo "\nTop 5 Salons:\n";
    $topSalons = DB::table('appointments')
        ->join('salons', 'appointments.salon_id', '=', 'salons.id')
        ->select('salons.name', DB::raw('count(*) as count'))
        ->groupBy('salons.id', 'salons.name')
        ->orderBy('count', 'desc')
        ->limit(5)
        ->get();

    foreach ($topSalons as $salon) {
        echo "  {$salon->name}: {$salon->count} bookings\n";
    }
}

/**
 * Show bookings by salon
 */
function showBookingsBySalon(int $salonId, int $limit)
{
    echo "🏢 Bookings for Salon #{$salonId}:\n\n";

    $salon = DB::table('salons')->find($salonId);
    if (!$salon) {
        echo "❌ Salon not found\n";
        return;
    }

    echo "Salon: {$salon->name}\n";
    echo "Location: {$salon->address}, {$salon->city}\n\n";

    $appointments = DB::table('appointments')
        ->join('staff', 'appointments.staff_id', '=', 'staff.id')
        ->join('services', 'appointments.service_id', '=', 'services.id')
        ->leftJoin('users', 'appointments.client_id', '=', 'users.id')
        ->select(
            'appointments.*',
            'staff.name as staff_name',
            'services.name as service_name',
            DB::raw('COALESCE(users.name, appointments.client_name) as client_name')
        )
        ->where('appointments.salon_id', $salonId)
        ->orderBy('appointments.created_at', 'desc')
        ->limit($limit)
        ->get();

    if ($appointments->isEmpty()) {
        echo "No bookings found for this salon.\n";
        return;
    }

    foreach ($appointments as $apt) {
        $statusIcon = getStatusIcon($apt->status);
        echo "{$statusIcon} {$apt->date} {$apt->time} - {$apt->client_name}\n";
        echo "   {$apt->service_name} with {$apt->staff_name}\n";
        echo "   Status: {$apt->status}\n\n";
    }

    echo "Total: " . $appointments->count() . " bookings\n";
}

/**
 * Show bookings by date
 */
function showBookingsByDate(string $date)
{
    echo "📅 Bookings for {$date}:\n\n";

    $appointments = DB::table('appointments')
        ->join('salons', 'appointments.salon_id', '=', 'salons.id')
        ->join('staff', 'appointments.staff_id', '=', 'staff.id')
        ->join('services', 'appointments.service_id', '=', 'services.id')
        ->leftJoin('users', 'appointments.client_id', '=', 'users.id')
        ->select(
            'appointments.*',
            'salons.name as salon_name',
            'staff.name as staff_name',
            'services.name as service_name',
            DB::raw('COALESCE(users.name, appointments.client_name) as client_name')
        )
        ->whereDate('appointments.date', $date)
        ->orderBy('appointments.time')
        ->get();

    if ($appointments->isEmpty()) {
        echo "No bookings for this date.\n";
        return;
    }

    $bySalon = $appointments->groupBy('salon_name');

    foreach ($bySalon as $salonName => $group) {
        echo "\n{$salonName} ({$group->count()} bookings):\n";
        foreach ($group as $apt) {
            $statusIcon = getStatusIcon($apt->status);
            echo "  {$statusIcon} {$apt->time} - {$apt->client_name} ({$apt->service_name} with {$apt->staff_name})\n";
        }
    }

    echo "\nTotal: " . $appointments->count() . " bookings\n";
}

/**
 * Get status icon
 */
function getStatusIcon(string $status): string
{
    return match($status) {
        'confirmed' => '✅',
        'pending' => '⏳',
        'completed' => '🎉',
        'cancelled' => '❌',
        default => '❓',
    };
}

/**
 * Show usage
 */
function showUsage()
{
    echo <<<USAGE
Usage: php view_booking_history.php [command] [options]

Commands:
  recent [limit]        - Show recent bookings (default: 50)
  today                 - Show today's bookings
  failed [limit]        - Show failed booking jobs
  pending               - Show pending bookings
  stats                 - Show booking statistics
  by-salon [id] [limit] - Show bookings for specific salon
  by-date [date]        - Show bookings for specific date (YYYY-MM-DD)

Examples:
  php view_booking_history.php recent 100
  php view_booking_history.php today
  php view_booking_history.php stats
  php view_booking_history.php by-salon 1 50
  php view_booking_history.php by-date 2024-12-26

USAGE;
}
