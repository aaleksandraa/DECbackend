<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Salon;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DailyReportService
{
    /**
     * Generate complete daily report for a salon.
     */
    public function generateReport(Salon $salon, Carbon $date): array
    {
        $settings = $salon->settings;

        $report = [
            'salon' => [
                'name' => $salon->name,
                'date' => $date->locale('bs')->isoFormat('dddd, D. MMMM YYYY.'),
                'date_short' => $date->format('d.m.Y'),
                'day_of_week' => $date->locale('bs')->isoFormat('dddd'),
            ],
            'overview' => $this->getOverview($salon, $date),
        ];

        // Add optional sections based on settings
        if (!$settings || $settings->daily_report_include_staff) {
            $report['staff_performance'] = $this->getStaffPerformance($salon, $date);
        }

        if (!$settings || $settings->daily_report_include_services) {
            $report['service_insights'] = $this->getServiceInsights($salon, $date);
        }

        if (!$settings || $settings->daily_report_include_capacity) {
            $report['capacity'] = $this->getCapacityUtilization($salon, $date);
        }

        if (!$settings || $settings->daily_report_include_cancellations) {
            $report['cancellations'] = $this->getCancellations($salon, $date);
        }

        $report['summary'] = $this->generateSummary($salon, $date, $report);

        return $report;
    }

    /**
     * Get daily overview metrics.
     */
    private function getOverview(Salon $salon, Carbon $date): array
    {
        $dateStr = $date->format('Y-m-d');

        // Get completed appointments for revenue
        $completedAppointments = Appointment::where('salon_id', $salon->id)
            ->whereDate('date', $dateStr)
            ->where('status', 'completed')
            ->get();

        // Get all appointments for counts
        $allAppointments = Appointment::where('salon_id', $salon->id)
            ->whereDate('date', $dateStr)
            ->whereIn('status', ['completed', 'in_progress', 'confirmed'])
            ->get();

        $totalRevenue = $completedAppointments->sum('total_price');
        $totalAppointments = $allAppointments->count();
        $uniqueClients = $allAppointments->unique('client_id')->count();
        $averageValue = $totalAppointments > 0 ? $totalRevenue / $totalAppointments : 0;

        // Calculate trend (compare with average of last 7 days, excluding today)
        $weekAgo = $date->copy()->subDays(7)->format('Y-m-d');
        $yesterday = $date->copy()->subDay()->format('Y-m-d');

        $avgRevenue = Appointment::where('salon_id', $salon->id)
            ->whereBetween('date', [$weekAgo, $yesterday])
            ->where('status', 'completed')
            ->avg('total_price');

        $trend = null;
        if ($avgRevenue > 0 && $totalAppointments > 0) {
            $trendPercent = (($averageValue - $avgRevenue) / $avgRevenue) * 100;
            $trend = [
                'percent' => round($trendPercent, 1),
                'direction' => $trendPercent > 0 ? 'up' : ($trendPercent < 0 ? 'down' : 'stable'),
            ];
        }

        return [
            'total_revenue' => $totalRevenue,
            'total_revenue_formatted' => number_format($totalRevenue, 2, ',', '.') . ' KM',
            'total_appointments' => $totalAppointments,
            'unique_clients' => $uniqueClients,
            'average_value' => $averageValue,
            'average_value_formatted' => number_format($averageValue, 2, ',', '.') . ' KM',
            'trend' => $trend,
        ];
    }

    /**
     * Get staff performance breakdown.
     */
    private function getStaffPerformance(Salon $salon, Carbon $date): array
    {
        $dateStr = $date->format('Y-m-d');

        $staffPerformance = Appointment::where('salon_id', $salon->id)
            ->whereDate('date', $dateStr)
            ->where('status', 'completed')
            ->with('staff:id,name')
            ->select(
                'staff_id',
                DB::raw('COUNT(*) as appointment_count'),
                DB::raw('SUM(total_price) as total_revenue')
            )
            ->groupBy('staff_id')
            ->orderByDesc('total_revenue')
            ->get();

        $totalRevenue = $staffPerformance->sum('total_revenue');

        $staff = $staffPerformance->map(function ($item) use ($totalRevenue) {
            $percentage = $totalRevenue > 0 ? ($item->total_revenue / $totalRevenue) * 100 : 0;

            return [
                'name' => $item->staff->name ?? 'Nepoznat',
                'appointments' => $item->appointment_count,
                'revenue' => $item->total_revenue,
                'revenue_formatted' => number_format($item->total_revenue, 2, ',', '.') . ' KM',
                'percentage' => round($percentage, 1),
            ];
        })->toArray();

        return [
            'staff' => $staff,
            'total_revenue' => $totalRevenue,
            'total_appointments' => $staffPerformance->sum('appointment_count'),
        ];
    }

    /**
     * Get service insights and top services.
     */
    private function getServiceInsights(Salon $salon, Carbon $date): array
    {
        $dateStr = $date->format('Y-m-d');

        $serviceStats = Appointment::where('salon_id', $salon->id)
            ->whereDate('date', $dateStr)
            ->where('status', 'completed')
            ->with('service:id,name')
            ->select(
                'service_id',
                DB::raw('COUNT(*) as service_count'),
                DB::raw('SUM(total_price) as total_revenue')
            )
            ->groupBy('service_id')
            ->orderByDesc('service_count')
            ->limit(5)
            ->get();

        $totalRevenue = $serviceStats->sum('total_revenue');
        $totalCount = $serviceStats->sum('service_count');

        $services = $serviceStats->map(function ($item) use ($totalRevenue, $totalCount) {
            $revenuePercentage = $totalRevenue > 0 ? ($item->total_revenue / $totalRevenue) * 100 : 0;
            $countPercentage = $totalCount > 0 ? ($item->service_count / $totalCount) * 100 : 0;

            return [
                'name' => $item->service->name ?? 'Nepoznata usluga',
                'count' => $item->service_count,
                'revenue' => $item->total_revenue,
                'revenue_formatted' => number_format($item->total_revenue, 2, ',', '.') . ' KM',
                'revenue_percentage' => round($revenuePercentage, 1),
                'count_percentage' => round($countPercentage, 1),
            ];
        })->toArray();

        return [
            'top_services' => $services,
            'total_revenue' => $totalRevenue,
            'total_count' => $totalCount,
        ];
    }

    /**
     * Get capacity utilization metrics.
     */
    private function getCapacityUtilization(Salon $salon, Carbon $date): array
    {
        $dateStr = $date->format('Y-m-d');

        // Get working hours for the salon
        $workingHours = $this->getWorkingHoursForDate($salon, $date);

        if (!$workingHours) {
            return [
                'available_slots' => 0,
                'occupied_slots' => 0,
                'cancelled_slots' => 0,
                'free_slots' => 0,
                'utilization_percentage' => 0,
                'periods' => [],
            ];
        }

        // Calculate total available slots (assuming 30-min slots)
        $startTime = Carbon::parse($workingHours['start']);
        $endTime = Carbon::parse($workingHours['end']);
        $totalMinutes = $endTime->diffInMinutes($startTime);
        $availableSlots = floor($totalMinutes / 30); // 30-minute slots

        // Get appointment statistics
        $occupiedCount = Appointment::where('salon_id', $salon->id)
            ->whereDate('date', $dateStr)
            ->whereIn('status', ['completed', 'in_progress', 'confirmed'])
            ->count();

        $cancelledCount = Appointment::where('salon_id', $salon->id)
            ->whereDate('date', $dateStr)
            ->whereIn('status', ['cancelled', 'no_show'])
            ->count();

        $freeSlots = max(0, $availableSlots - $occupiedCount);
        $utilizationPercentage = $availableSlots > 0 ? ($occupiedCount / $availableSlots) * 100 : 0;

        // Analyze by period
        $periods = $this->analyzeByPeriod($salon, $date, $workingHours);

        return [
            'available_slots' => $availableSlots,
            'occupied_slots' => $occupiedCount,
            'cancelled_slots' => $cancelledCount,
            'free_slots' => $freeSlots,
            'utilization_percentage' => round($utilizationPercentage, 1),
            'periods' => $periods,
        ];
    }

    /**
     * Analyze capacity by time periods (morning, afternoon, evening).
     */
    private function analyzeByPeriod(Salon $salon, Carbon $date, array $workingHours): array
    {
        $dateStr = $date->format('Y-m-d');

        $periods = [
            ['name' => 'Jutro', 'start' => '08:00', 'end' => '12:00'],
            ['name' => 'Popodne', 'start' => '12:00', 'end' => '16:00'],
            ['name' => 'Večer', 'start' => '16:00', 'end' => '20:00'],
        ];

        $result = [];

        foreach ($periods as $period) {
            $count = Appointment::where('salon_id', $salon->id)
                ->whereDate('date', $dateStr)
                ->whereTime('time', '>=', $period['start'])
                ->whereTime('time', '<', $period['end'])
                ->whereIn('status', ['completed', 'in_progress', 'confirmed'])
                ->count();

            // Calculate period capacity (4 hours = 8 slots of 30 min)
            $periodMinutes = 240; // 4 hours
            $periodSlots = floor($periodMinutes / 30);
            $percentage = $periodSlots > 0 ? ($count / $periodSlots) * 100 : 0;

            $result[] = [
                'name' => $period['name'],
                'time_range' => $period['start'] . ' - ' . $period['end'],
                'appointments' => $count,
                'percentage' => round($percentage, 1),
            ];
        }

        return $result;
    }

    /**
     * Get cancellation statistics.
     */
    private function getCancellations(Salon $salon, Carbon $date): array
    {
        $dateStr = $date->format('Y-m-d');

        $cancelled = Appointment::where('salon_id', $salon->id)
            ->whereDate('date', $dateStr)
            ->where('status', 'cancelled')
            ->get();

        $noShow = Appointment::where('salon_id', $salon->id)
            ->whereDate('date', $dateStr)
            ->where('status', 'no_show')
            ->get();

        $totalCancelled = $cancelled->count();
        $totalNoShow = $noShow->count();
        $estimatedLoss = $cancelled->sum('total_price') + $noShow->sum('total_price');

        return [
            'cancelled_count' => $totalCancelled,
            'no_show_count' => $totalNoShow,
            'total_count' => $totalCancelled + $totalNoShow,
            'estimated_loss' => $estimatedLoss,
            'estimated_loss_formatted' => number_format($estimatedLoss, 2, ',', '.') . ' KM',
        ];
    }

    /**
     * Generate AI-like summary based on the data.
     */
    private function generateSummary(Salon $salon, Carbon $date, array $report): string
    {
        $overview = $report['overview'];
        $capacity = $report['capacity'] ?? null;

        $summary = '';

        // Revenue assessment
        if ($overview['total_revenue'] == 0) {
            $summary = 'Dan nije imao aktivnosti. ';
        } elseif ($overview['trend'] && $overview['trend']['direction'] === 'up') {
            $summary = 'Dan je bio iznad proseka sa visokim prometom. ';
        } elseif ($overview['trend'] && $overview['trend']['direction'] === 'down') {
            $summary = 'Dan je bio ispod proseka. ';
        } else {
            $summary = 'Dan je bio u skladu sa očekivanjima. ';
        }

        // Capacity assessment
        if ($capacity) {
            if ($capacity['utilization_percentage'] >= 80) {
                $summary .= 'Visoka popunjenost termina (' . $capacity['utilization_percentage'] . '%). ';
            } elseif ($capacity['utilization_percentage'] >= 50) {
                $summary .= 'Umjerena popunjenost termina (' . $capacity['utilization_percentage'] . '%). ';
            } else {
                $summary .= 'Niska popunjenost termina (' . $capacity['utilization_percentage'] . '%). ';
            }

            // Period-specific insights
            if (!empty($capacity['periods'])) {
                $lowestPeriod = collect($capacity['periods'])->sortBy('percentage')->first();
                if ($lowestPeriod && $lowestPeriod['percentage'] < 50) {
                    $summary .= 'Preporučuje se fokus na promociju ' . strtolower($lowestPeriod['name']) . ' termina.';
                }
            }
        }

        // Cancellation warning
        if (isset($report['cancellations']) && $report['cancellations']['total_count'] > 3) {
            $summary .= ' Primijećen je veći broj otkazivanja (' . $report['cancellations']['total_count'] . ').';
        }

        return trim($summary);
    }

    /**
     * Get working hours for a specific date.
     */
    private function getWorkingHoursForDate(Salon $salon, Carbon $date): ?array
    {
        if (!$salon->working_hours) {
            return ['start' => '08:00', 'end' => '20:00']; // Default
        }

        $dayOfWeek = strtolower($date->locale('en')->dayName);
        $dayMapping = [
            'monday' => 'monday',
            'tuesday' => 'tuesday',
            'wednesday' => 'wednesday',
            'thursday' => 'thursday',
            'friday' => 'friday',
            'saturday' => 'saturday',
            'sunday' => 'sunday',
        ];

        $day = $dayMapping[$dayOfWeek] ?? null;

        if (!$day || !isset($salon->working_hours[$day])) {
            return null;
        }

        $hours = $salon->working_hours[$day];

        if (!$hours['is_open'] ?? false) {
            return null;
        }

        return [
            'start' => $hours['open'] ?? '08:00',
            'end' => $hours['close'] ?? '20:00',
        ];
    }
}
