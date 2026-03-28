<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ClientController extends Controller
{
    /**
     * Get clients for salon owner or staff member.
     * Supports filtering by staff, service(s), service category and last visit period.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        [$salonId] = $this->resolveSalonContext($user);
        if (!$salonId) {
            return response()->json(['message' => 'Salon not found'], 404);
        }

        $search = trim((string) $request->input('search', ''));
        $perPage = max(1, min((int) $request->input('per_page', 20), 500));
        $page = max(1, (int) $request->input('page', 1));
        $sortBy = (string) $request->input('sort_by', 'last_visit');
        $sortDirection = strtolower((string) $request->input('sort_direction', 'desc'));
        $lastVisitFilter = (string) $request->input('last_visit_filter', 'all');

        if (!in_array($sortBy, ['name', 'total_appointments', 'total_spent', 'last_visit', 'member_since'], true)) {
            $sortBy = 'last_visit';
        }

        if (!in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $staffIds = $this->normalizeIntArray($request->input('staff_ids', []));
        $serviceIds = $this->normalizeIntArray($request->input('service_ids', []));
        $serviceCategories = $this->normalizeStringArray($request->input('service_categories', []));

        $serviceOptions = Service::query()
            ->where('salon_id', $salonId)
            ->orderBy('name')
            ->get(['id', 'name', 'category']);

        $categoryOptions = $serviceOptions
            ->pluck('category')
            ->filter(fn($category) => is_string($category) && trim($category) !== '')
            ->map(fn($category) => trim((string) $category))
            ->unique()
            ->sort()
            ->values();

        $categoryServiceIds = [];
        if (!empty($serviceCategories)) {
            $categoryServiceIds = $serviceOptions
                ->whereIn('category', $serviceCategories)
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->all();
        }

        $resolvedServiceIds = array_values(array_unique(array_merge($serviceIds, $categoryServiceIds)));

        $staffOptions = Staff::query()
            ->with('user:id,name')
            ->where('salon_id', $salonId)
            ->orderBy('name')
            ->get(['id', 'name', 'user_id'])
            ->map(function (Staff $staff) {
                $displayName = trim((string) ($staff->name ?: ($staff->user?->name ?? '')));
                if ($displayName === '') {
                    $displayName = 'Zaposleni #' . $staff->id;
                }

                return [
                    'id' => (int) $staff->id,
                    'name' => $displayName,
                ];
            })
            ->values();

        if ((!empty($serviceIds) || !empty($serviceCategories)) && empty($resolvedServiceIds)) {
            return response()->json([
                'clients' => [],
                'total' => 0,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => 0,
                'filters' => [
                    'staff' => $staffOptions,
                    'services' => $serviceOptions->map(fn($service) => [
                        'id' => (int) $service->id,
                        'name' => $service->name,
                        'category' => $service->category,
                    ])->values(),
                    'categories' => $categoryOptions,
                ],
                'applied_filters' => [
                    'search' => $search,
                    'last_visit_filter' => $lastVisitFilter,
                    'staff_ids' => $staffIds,
                    'service_ids' => $serviceIds,
                    'service_categories' => $serviceCategories,
                ],
            ]);
        }

        $query = Appointment::query()
            ->join('users', 'users.id', '=', 'appointments.client_id')
            ->where('appointments.salon_id', $salonId)
            ->where('appointments.status', '!=', 'cancelled');

        if (!empty($staffIds)) {
            $query->whereIn('appointments.staff_id', $staffIds);
        }

        if (!empty($resolvedServiceIds)) {
            $this->applyServiceFilter($query, $resolvedServiceIds);
        }

        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $searchLike = '%' . $searchLower . '%';

            $query->where(function ($q) use ($searchLike) {
                $q->whereRaw('LOWER(users.name) LIKE ?', [$searchLike])
                    ->orWhereRaw('LOWER(users.email) LIKE ?', [$searchLike])
                    ->orWhereRaw('LOWER(COALESCE(users.phone, \'\')) LIKE ?', [$searchLike]);
            });
        }

        $query
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.phone',
                'users.avatar',
            ])
            ->selectRaw('COUNT(appointments.id) as total_appointments')
            ->selectRaw("SUM(CASE WHEN appointments.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments")
            ->selectRaw("SUM(CASE WHEN appointments.status = 'completed' THEN COALESCE(appointments.total_price, 0) ELSE 0 END) as total_spent")
            ->selectRaw('MAX(appointments.date) as last_visit')
            ->selectRaw('MIN(appointments.created_at) as member_since')
            ->groupBy('users.id', 'users.name', 'users.email', 'users.phone', 'users.avatar');

        $cutoffDate = $this->resolveLastVisitCutoffDate($lastVisitFilter);
        if ($cutoffDate) {
            $query->havingRaw('MAX(appointments.date) >= ?', [$cutoffDate->format('Y-m-d')]);
        }

        $sortColumns = [
            'name' => 'users.name',
            'total_appointments' => 'total_appointments',
            'total_spent' => 'total_spent',
            'last_visit' => 'last_visit',
            'member_since' => 'member_since',
        ];
        $query->orderBy($sortColumns[$sortBy] ?? 'last_visit', $sortDirection);

        $allClients = $query->get()->map(function ($client) {
            $lastVisit = $client->last_visit ? Carbon::parse($client->last_visit)->format('Y-m-d') : null;
            $memberSince = $client->member_since ? Carbon::parse($client->member_since)->toIso8601String() : null;

            return [
                'id' => (int) $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'avatar' => $client->avatar,
                'total_appointments' => (int) $client->total_appointments,
                'completed_appointments' => (int) $client->completed_appointments,
                'last_visit' => $lastVisit,
                'total_spent' => round((float) $client->total_spent, 2),
                'member_since' => $memberSince,
            ];
        });

        $total = $allClients->count();
        $clients = $allClients->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'clients' => $clients,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $total > 0 ? (int) ceil($total / $perPage) : 0,
            'filters' => [
                'staff' => $staffOptions,
                'services' => $serviceOptions->map(fn($service) => [
                    'id' => (int) $service->id,
                    'name' => $service->name,
                    'category' => $service->category,
                ])->values(),
                'categories' => $categoryOptions,
            ],
            'applied_filters' => [
                'search' => $search,
                'last_visit_filter' => $lastVisitFilter,
                'staff_ids' => $staffIds,
                'service_ids' => $serviceIds,
                'service_categories' => $serviceCategories,
            ],
        ]);
    }

    /**
     * Get client details with appointment history.
     */
    public function show(Request $request, int $clientId): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        [$salonId] = $this->resolveSalonContext($user);
        if (!$salonId) {
            return response()->json(['message' => 'Salon not found'], 404);
        }

        $client = User::find($clientId);

        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        $appointments = Appointment::with(['service', 'staff.user'])
            ->where('client_id', $clientId)
            ->where('salon_id', $salonId)
            ->orderBy('date', 'desc')
            ->orderBy('time', 'desc')
            ->get();

        $totalSpent = $appointments->where('status', 'completed')->sum('total_price');
        $totalAppointments = $appointments->where('status', '!=', 'cancelled')->count();

        return response()->json([
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'avatar' => $client->avatar,
                'created_at' => $client->created_at,
            ],
            'stats' => [
                'total_appointments' => $totalAppointments,
                'completed_appointments' => $appointments->where('status', 'completed')->count(),
                'cancelled_appointments' => $appointments->where('status', 'cancelled')->count(),
                'total_spent' => $totalSpent,
            ],
            'appointments' => $appointments->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'date' => $appointment->date->format('Y-m-d'),
                    'time' => $appointment->time,
                    'status' => $appointment->status,
                    'total_price' => $appointment->total_price,
                    'services' => [$appointment->service ? $appointment->service->name : 'N/A'],
                    'staff' => $appointment->staff && $appointment->staff->user ? $appointment->staff->user->name : null,
                    'notes' => $appointment->notes,
                ];
            }),
        ]);
    }

    /**
     * Send email to selected clients.
     * Supports personalization placeholders: {ime}, {korisnicko_ime}, {name}
     */
    public function sendEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        [$salonId, $salonName] = $this->resolveSalonContext($user);
        if (!$salonId) {
            return response()->json(['message' => 'Salon not found'], 404);
        }

        $validated = $request->validate([
            'client_ids' => 'required|array|min:1',
            'client_ids.*' => 'integer|exists:users,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:10000',
        ]);

        $clientIds = array_values(array_unique(array_map('intval', $validated['client_ids'])));
        $subjectTemplate = $validated['subject'];
        $messageTemplate = $validated['message'];

        // Security: only allow emailing clients who have appointments at this salon.
        $allowedClientIds = Appointment::query()
            ->where('salon_id', $salonId)
            ->where('status', '!=', 'cancelled')
            ->whereIn('client_id', $clientIds)
            ->distinct()
            ->pluck('client_id')
            ->map(fn($id) => (int) $id)
            ->all();

        if (empty($allowedClientIds)) {
            return response()->json([
                'message' => 'Nijedan izabrani korisnik nije klijent ovog salona.',
                'sent' => 0,
                'failed' => 0,
                'skipped_not_client' => count($clientIds),
                'skipped_missing_email' => 0,
            ], 422);
        }

        $clients = User::whereIn('id', $allowedClientIds)->get();

        $sentCount = 0;
        $failedCount = 0;
        $skippedNoEmail = 0;
        $fromName = trim($salonName) !== '' ? $salonName : (string) config('mail.from.name');

        foreach ($clients as $client) {
            if (empty($client->email)) {
                $skippedNoEmail++;
                continue;
            }

            try {
                $subject = $this->personalizeTemplate($subjectTemplate, $client);
                $message = $this->personalizeTemplate($messageTemplate, $client);

                Mail::raw($message, function ($mail) use ($client, $subject, $fromName) {
                    $mail->to($client->email)
                        ->subject($subject)
                        ->from((string) config('mail.from.address'), $fromName);
                });

                $sentCount++;
            } catch (\Throwable $e) {
                $failedCount++;
                \Log::error('Failed to send client email', [
                    'client_id' => $client->id,
                    'salon_id' => $salonId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $skippedNotClient = count(array_diff($clientIds, $allowedClientIds));

        return response()->json([
            'message' => "Email poslat na {$sentCount} klijenata.",
            'sent' => $sentCount,
            'failed' => $failedCount,
            'skipped_not_client' => $skippedNotClient,
            'skipped_missing_email' => $skippedNoEmail,
            'placeholders' => ['{ime}', '{korisnicko_ime}', '{name}'],
        ]);
    }

    /**
     * Resolve salon context for salon owner or staff user.
     *
     * @return array{0:?int,1:string}
     */
    private function resolveSalonContext(User $user): array
    {
        if ($user->role === 'salon') {
            $salon = Salon::where('owner_id', $user->id)->first();
            return [$salon?->id, (string) ($salon?->name ?? '')];
        }

        if ($user->role === 'frizer') {
            $staff = Staff::where('user_id', $user->id)->first();
            if (!$staff) {
                return [null, ''];
            }

            $salon = Salon::find($staff->salon_id);
            return [(int) $staff->salon_id, (string) ($salon?->name ?? '')];
        }

        return [null, ''];
    }

    /**
     * Normalize incoming value into unique positive integer array.
     */
    private function normalizeIntArray(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (is_numeric($item)) {
                $intValue = (int) $item;
                if ($intValue > 0) {
                    $normalized[] = $intValue;
                }
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Normalize incoming value into unique non-empty string array.
     */
    private function normalizeStringArray(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $trimmed = trim($item);
            if ($trimmed !== '') {
                $normalized[] = $trimmed;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Apply filtering by single/multiple services (supports service_id + JSON service_ids).
     */
    private function applyServiceFilter($query, array $serviceIds): void
    {
        $query->where(function ($q) use ($serviceIds) {
            $q->whereIn('appointments.service_id', $serviceIds);

            foreach ($serviceIds as $serviceId) {
                $q->orWhereJsonContains('appointments.service_ids', $serviceId)
                    ->orWhereJsonContains('appointments.service_ids', (string) $serviceId);
            }
        });
    }

    /**
     * Convert last visit filter key to cutoff date.
     */
    private function resolveLastVisitCutoffDate(string $filter): ?Carbon
    {
        $filter = trim(strtolower($filter));
        if ($filter === '' || $filter === 'all') {
            return null;
        }

        $daysMap = [
            'week' => 7,
            'month' => 30,
            '3months' => 90,
            '6months' => 180,
            'year' => 365,
        ];

        if (isset($daysMap[$filter])) {
            return now()->subDays($daysMap[$filter])->startOfDay();
        }

        if (preg_match('/^(\d+)\s*d$/', $filter, $matches)) {
            return now()->subDays((int) $matches[1])->startOfDay();
        }

        return null;
    }

    /**
     * Replace personalization placeholders in subject/message.
     */
    private function personalizeTemplate(string $template, User $client): string
    {
        $fullName = trim((string) $client->name);
        $firstName = $fullName !== '' ? explode(' ', $fullName)[0] : 'klijente';

        return strtr($template, [
            '{ime}' => $firstName,
            '{{ime}}' => $firstName,
            '{korisnicko_ime}' => $fullName,
            '{{korisnicko_ime}}' => $fullName,
            '{name}' => $fullName,
            '{{name}}' => $fullName,
        ]);
    }
}

