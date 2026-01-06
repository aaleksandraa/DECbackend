<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Salon;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MonthlyReportService
{
    /**
     * Generate complete monthly report for a salon.
     */
    public function generateReport(Salon $salon, Carbon $month): array
    {
        $settings = $salon->settings;

        // Get first and last day of month
        $startDate = $month->copy()->startOfMonth();
        $endDate = $month->copy()->endOfMonth();

        $report = [
            'salon' => [
                'name' => $salon->name,
                'month' => $month->locale('bs')->isoFormat('MMMM YYYY.'),
                'month_short' => $month->format('m.Y'),
                'period' => $startDate->format('d.m.Y') . ' - ' . $endDate->format('d.m.Y'),
            ],
            'overview' => $this->getOverview($salon, $startDate, $endDate),
            'staff_performance' => $this->getStaffPerformance($salon, $startDate, $endDate),
            'service_insights' => $this->getServiceInsights($salon, $startDate, $endDate),
            'daily_breakdown' => $this->getDailyBreakdown($salon, $startDate, $endDate),
            'trends' => $this->getTrends($salon, $startDate, $endDate),
            'top_clients' => $this->getTopClients($salon, $startDate, $endDate),
        ];

        $report['summary'] = $this->generateSummary($salon, $startDate, $endDate, $report);

        return $report;
    }

    /**
     * Get monthly overview metrics.
     */
    private function getOverview(Salon $salon, Carbon $startDate, Carbon $endDate): array
    {
        // Get completed appointments for revenue
        $completedAppointments = Appointment::where('salon_id', $salon->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('status', 'completed')
            ->get();

        // Get all appointments for counts
        $allAppointments = Appointment::where('salon_id', $salon->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->whereIn('status', ['completed', 'in_progress', 'confirmed'])
            ->get();

        $totalRevenue = $completedAppointments->sum('total_price');
        $totalAppointments = $allAppointments->count();
        $uniqueClients = $allAppointments->unique('client_id')->count();
        $averageValue = $totalAppointments > 0 ? $totalRevenue / $totalAppointments : 0;

        // Calculate working days in month
        $workingDays = $this->getWorkingDaysCount($salon, $startDate, $endDate);
        $avgRevenuePerDay = $workingDays > 0 ? $totalRevenue / $workingDays : 0;
        $avgAppointmentsPerDay = $workingDays > 0 ? $totalAppointments / $workingDays : 0;

        // Compare with previous month
        $prevMonthStart = $startDate->copy()->subMonth()->startOfMonth();
        $prevMonthEnd = $startDate->copy()->subMonth()->endOfMonth();

        $prevMonthRevenue = Appointment::where('salon_id', $salon->id)
            ->whereBetween('date', [$prevMonthStart->format('Y-m-d'), $prevMonthEnd->format('Y-m-d')])
            ->where('status', 'completed')
            ->sum('total_price');

        $revenueGrowth = null;
        if ($prevMonthRevenue > 0) {
            $growthPercent = (($totalRevenue - $prevMonthRevenue) / $prevMonthRevenue) * 100;
            $revenueGrowth = [
                'percent' => round($growthPercent, 1),
                'direction' => $growthPercent > 0 ? 'up' : ($growthPercent < 0 ? 'down' : 'stable'),
                'amount' => $totalRevenue - $prevMonthRevenue,
            ];
        }

        return [
            'total_revenue' => $totalRevenue,
            'total_revenue_formatted' => number_format($totalRevenue, 2, ',', '.') . ' KM',
            'total_appointments' => $totalAppointments,
            'unique_clients' => $uniqueClients,
            'average_value' => $averageValue,
            'average_value_formatted' => number_format($averageValue, 2, ',', '.') . ' KM',
            'working_days' => $workingDays,
            'avg_revenue_per_day' => $avgRevenuePerDay,
            'avg_revenue_per_day_formatted' => number_format($avgRevenuePerDay, 2, ',', '.') . ' KM',
            'avg_appointments_per_day' => round($avgAppointmentsPerDay, 1),
            'revenue_growth' => $revenueGrowth,
        ];
    }

    /**
     * Get staff performance for the month.
     */
    private function getStaffPerformance(Salon $salon, Carbon $startDate, Carbon $endDate): array
    {
        $staffPerformance = Appointment::where('salon_id', $salon->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('status', 'completed')
            ->with('staff:id,name')
            ->select(
                'staff_id',
                DB::raw('COUNT(*) as appointment_count'),
                DB::raw('SUM(total_price) as total_revenue'),
                DB::raw('AVG(total_price) as avg_revenue')
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
                'avg_revenue' => $item->avg_revenue,
                'avg_revenue_formatted' => number_format($item->avg_revenue, 2, ',', '.') . ' KM',
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
     * Get service insights for the month.
     */
    private function getServiceInsights(Salon $salon, Carbon $startDate, Carbon $endDate): array
    {
        $serviceStats = Appointment::where('salon_id', $salon->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('status', 'completed')
            ->with('service:id,name')
            ->select(
                'service_id',
                DB::raw('COUNT(*) as service_count'),
                DB::raw('SUM(total_price) as total_revenue'),
                DB::raw('AVG(total_price) as avg_price')
            )
            ->groupBy('service_id')
            ->orderByDesc('total_revenue')
            ->limit(10)
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
                'avg_price' => $item->avg_price,
                'avg_price_formatted' => number_format($item->avg_price, 2, ',', '.') . ' KM',
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
     * Get daily breakdown for the month.
     */
    private function getDailyBreakdown(Salon $salon, Carbon $startDate, Carbon $endDate): array
    {
        $dailyStats = Appointment::where('salon_id', $salon->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('status', 'completed')
            ->select(
                'date',
                DB::raw('COUNT(*) as appointment_count'),
                DB::raw('SUM(total_price) as daily_revenue')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $days = $dailyStats->map(function ($item) {
            $date = Carbon::parse($item->date);
            return [
                'date' => $date->format('d.m'),
                'day_name' => $date->locale('bs')->isoFormat('ddd'),
                'appointments' => $item->appointment_count,
                'revenue' => $item->daily_revenue,
                'revenue_formatted' => number_format($item->daily_revenue, 2, ',', '.') . ' KM',
            ];
        })->toArray();

        // Find best and worst days
        $bestDay = $dailyStats->sortByDesc('daily_revenue')->first();
        $worstDay = $dailyStats->sortBy('daily_revenue')->first();

        return [
            'days' => $days,
            'best_day' => $bestDay ? [
                'date' => Carbon::parse($bestDay->date)->format('d.m.Y'),
                'revenue' => $bestDay->daily_revenue,
                'revenue_formatted' => number_format($bestDay->daily_revenue, 2, ',', '.') . ' KM',
                'appointments' => $bestDay->appointment_count,
            ] : null,
            'worst_day' => $worstDay ? [
                'date' => Carbon::parse($worstDay->date)->format('d.m.Y'),
                'revenue' => $worstDay->daily_revenue,
                'revenue_formatted' => number_format($worstDay->daily_revenue, 2, ',', '.') . ' KM',
                'appointments' => $worstDay->appointment_count,
            ] : null,
        ];
    }

    /**
     * Get trends and patterns.
     */
    private function getTrends(Salon $salon, Carbon $startDate, Carbon $endDate): array
    {
        // Weekly breakdown
        $weeklyStats = Appointment::where('salon_id', $salon->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('status', 'completed')
            ->select(
                DB::raw('EXTRACT(WEEK FROM date) as week_number'),
                DB::raw('COUNT(*) as appointment_count'),
                DB::raw('SUM(total_price) as weekly_revenue')
            )
            ->groupBy('week_number')
            ->orderBy('week_number')
            ->get();

        $weeks = $weeklyStats->map(function ($item) {
            return [
                'week' => 'Sedmica ' . $item->week_number,
                'appointments' => $item->appointment_count,
                'revenue' => $item->weekly_revenue,
                'revenue_formatted' => number_format($item->weekly_revenue, 2, ',', '.') . ' KM',
            ];
        })->toArray();

        // Day of week analysis
        $dayOfWeekStats = Appointment::where('salon_id', $salon->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('status', 'completed')
            ->select(
                DB::raw('EXTRACT(DOW FROM date) as day_of_week'),
                DB::raw('COUNT(*) as appointment_count'),
                DB::raw('SUM(total_price) as revenue')
            )
            ->groupBy('day_of_week')
            ->orderBy('day_of_week')
            ->get();

        $dayNames = ['Nedjelja', 'Ponedjeljak', 'Utorak', 'Srijeda', 'Četvrtak', 'Petak', 'Subota'];

        $dayOfWeek = $dayOfWeekStats->map(function ($item) use ($dayNames) {
            return [
                'day' => $dayNames[$item->day_of_week] ?? 'Nepoznat',
                'appointments' => $item->appointment_count,
                'revenue' => $item->revenue,
                'revenue_formatted' => number_format($item->revenue, 2, ',', '.') . ' KM',
            ];
        })->toArray();

        return [
            'weekly' => $weeks,
            'day_of_week' => $dayOfWeek,
        ];
    }

    /**
     * Get top clients for the month.
     */
    private function getTopClients(Salon $salon, Carbon $startDate, Carbon $endDate): array
    {
        $topClients = Appointment::where('salon_id', $salon->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('status', 'completed')
            ->whereNotNull('client_id')
            ->with('client:id,name,email')
            ->select(
                'client_id',
                DB::raw('COUNT(*) as visit_count'),
                DB::raw('SUM(total_price) as total_spent')
            )
            ->groupBy('client_id')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get();

        $clients = $topClients->map(function ($item) {
            return [
                'name' => $item->client->name ?? 'Nepoznat',
                'email' => $item->client->email ?? null,
                'visits' => $item->visit_count,
                'total_spent' => $item->total_spent,
                'total_spent_formatted' => number_format($item->total_spent, 2, ',', '.') . ' KM',
                'avg_per_visit' => $item->visit_count > 0 ? $item->total_spent / $item->visit_count : 0,
                'avg_per_visit_formatted' => number_format($item->visit_count > 0 ? $item->total_spent / $item->visit_count : 0, 2, ',', '.') . ' KM',
            ];
        })->toArray();

        return [
            'clients' => $clients,
            'total_clients' => count($clients),
        ];
    }

    /**
     * Generate summary based on the data.
     */
    private function generateSummary(Salon $salon, Carbon $startDate, Carbon $endDate, array $report): string
    {
        $overview = $report['overview'];
        $trends = $report['trends'];

        $summary = '';

        // Revenue assessment
        if ($overview['revenue_growth']) {
            $growth = $overview['revenue_growth'];
            if ($growth['direction'] === 'up') {
                $summary = "Mjesec je bio uspješan sa rastom prihoda od {$growth['percent']}%. ";
            } elseif ($growth['direction'] === 'down') {
                $summary = "Mjesec je pokazao pad prihoda od " . abs($growth['percent']) . "%. ";
            } else {
                $summary = "Mjesec je bio stabilan sa prihodom sličnim prethodnom mjesecu. ";
            }
        } else {
            $summary = "Mjesec je ostvario ukupan prihod od {$overview['total_revenue_formatted']}. ";
        }

        // Daily average
        $summary .= "Prosječan dnevni prihod bio je {$overview['avg_revenue_per_day_formatted']} ";
        $summary .= "sa {$overview['avg_appointments_per_day']} termina dnevno. ";

        // Best performing day
        if (!empty($report['daily_breakdown']['best_day'])) {
            $bestDay = $report['daily_breakdown']['best_day'];
            $summary .= "Najbolji dan bio je {$bestDay['date']} sa prihodom od {$bestDay['revenue_formatted']}. ";
        }

        // Client retention
        if ($overview['unique_clients'] > 0) {
            $repeatRate = (($overview['total_appointments'] - $overview['unique_clients']) / $overview['unique_clients']) * 100;
            if ($repeatRate > 50) {
                $summary .= "Visoka stopa ponovljenih posjeta (" . round($repeatRate) . "%) ukazuje na zadovoljne klijente.";
            }
        }

        return trim($summary);
    }

    /**
     * Get working days count for the period.
     */
    private function getWorkingDaysCount(Salon $salon, Carbon $startDate, Carbon $endDate): int
    {
        if (!$salon->working_hours) {
            return $endDate->diffInDays($startDate) + 1;
        }

        $workingDays = 0;
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dayOfWeek = strtolower($current->locale('en')->dayName);
            $dayHours = $salon->working_hours[$dayOfWeek] ?? null;

            if ($dayHours && ($dayHours['is_open'] ?? false)) {
                $workingDays++;
            }

            $current->addDay();
        }

        return $workingDays;
    }
}
