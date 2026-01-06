<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalonResource;
use App\Http\Resources\UserResource;
use App\Mail\SalonApprovedMail;
use App\Models\Appointment;
use App\Models\Salon;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        // Middleware is defined in routes/api.php
    }

    /**
     * Get dashboard statistics.
     */
    public function dashboardStats(): JsonResponse
    {
        $totalSalons = Salon::count();
        $pendingSalons = Salon::pending()->count();
        $approvedSalons = Salon::approved()->count();
        $suspendedSalons = Salon::suspended()->count();

        $totalUsers = User::count();
        $adminUsers = User::where('role', 'admin')->count();
        $salonUsers = User::where('role', 'salon')->count();
        $staffUsers = User::where('role', 'frizer')->count();
        $clientUsers = User::where('role', 'klijent')->count();

        $totalAppointments = Appointment::count();
        $pendingAppointments = Appointment::where('status', 'pending')->count();
        $confirmedAppointments = Appointment::where('status', 'confirmed')->count();
        $completedAppointments = Appointment::where('status', 'completed')->count();
        $cancelledAppointments = Appointment::where('status', 'cancelled')->count();

        // Calculate platform revenue (10% of completed appointments)
        $totalRevenue = Appointment::where('status', 'completed')->sum('total_price');
        $platformRevenue = $totalRevenue * 0.1;

        return response()->json([
            'salons' => [
                'total' => $totalSalons,
                'pending' => $pendingSalons,
                'approved' => $approvedSalons,
                'suspended' => $suspendedSalons,
            ],
            'users' => [
                'total' => $totalUsers,
                'admin' => $adminUsers,
                'salon' => $salonUsers,
                'staff' => $staffUsers,
                'client' => $clientUsers,
            ],
            'appointments' => [
                'total' => $totalAppointments,
                'pending' => $pendingAppointments,
                'confirmed' => $confirmedAppointments,
                'completed' => $completedAppointments,
                'cancelled' => $cancelledAppointments,
            ],
            'revenue' => [
                'total' => $totalRevenue,
                'platform' => $platformRevenue,
            ],
        ]);
    }

    /**
     * Get all users.
     */
    public function users(Request $request): AnonymousResourceCollection
    {
        $query = User::query();

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Search by name or email
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return UserResource::collection($users);
    }

    /**
     * Get all salons.
     */
    public function salons(Request $request): AnonymousResourceCollection
    {
        $query = Salon::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by name, city, or owner
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('city', 'like', '%' . $request->search . '%')
                  ->orWhereHas('owner', function ($q) use ($request) {
                      $q->where('name', 'like', '%' . $request->search . '%')
                        ->orWhere('email', 'like', '%' . $request->search . '%');
                  });
            });
        }

        $salons = $query->with(['owner', 'images'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return SalonResource::collection($salons);
    }

    /**
     * Approve a salon.
     */
    public function approveSalon(Salon $salon): JsonResponse
    {
        if ($salon->status === 'approved') {
            return response()->json([
                'message' => 'Salon is already approved',
            ], 422);
        }

        $salon->update(['status' => 'approved']);

        // Send notification to salon owner
        $this->notificationService->sendSalonStatusChangeNotification($salon, 'approved');

        // Send email notification to salon owner
        try {
            $salon->load('owner');
            if ($salon->owner && $salon->owner->email) {
                Mail::to($salon->owner->email)->send(new SalonApprovedMail($salon));
                Log::info('Salon approval email sent', [
                    'salon_id' => $salon->id,
                    'owner_email' => $salon->owner->email
                ]);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the approval
            Log::error('Failed to send salon approval email', [
                'salon_id' => $salon->id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'message' => 'Salon approved successfully',
            'salon' => new SalonResource($salon),
        ]);
    }

    /**
     * Suspend a salon.
     */
    public function suspendSalon(Salon $salon): JsonResponse
    {
        if ($salon->status === 'suspended') {
            return response()->json([
                'message' => 'Salon is already suspended',
            ], 422);
        }

        $salon->update(['status' => 'suspended']);

        // Send notification to salon owner
        $this->notificationService->sendSalonStatusChangeNotification($salon, 'suspended');

        return response()->json([
            'message' => 'Salon suspended successfully',
            'salon' => new SalonResource($salon),
        ]);
    }

    /**
     * Get analytics data.
     */
    public function analytics(Request $request): JsonResponse
    {
        $period = $request->period ?? 'month'; // day, week, month, year
        $startDate = null;
        $endDate = now();

        switch ($period) {
            case 'day':
                $startDate = now()->subDay();
                break;
            case 'week':
                $startDate = now()->subWeek();
                break;
            case 'month':
                $startDate = now()->subMonth();
                break;
            case 'year':
                $startDate = now()->subYear();
                break;
            default:
                $startDate = now()->subMonth();
        }

        // Get new users by day - PostgreSQL compatible
        $newUsers = User::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as count')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get();

        // Get new salons by day - PostgreSQL compatible
        $newSalons = Salon::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as count')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get();

        // Get appointments by day - PostgreSQL compatible
        $appointments = Appointment::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as count')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get();

        // Get revenue by day - PostgreSQL compatible
        $revenue = Appointment::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('SUM(total_price) as total')
            ->selectRaw('SUM(total_price) * 0.1 as platform')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get();

        // Get top cities
        $topCities = Salon::selectRaw('city')
            ->selectRaw('COUNT(*) as salon_count')
            ->groupBy('city')
            ->orderBy('salon_count', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'period' => $period,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'new_users' => $newUsers,
            'new_salons' => $newSalons,
            'appointments' => $appointments,
            'revenue' => $revenue,
            'top_cities' => $topCities,
        ]);
    }

    /**
     * Create a new user.
     */
    public function createUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:255',
            'password' => 'required|string|min:6',
            'role' => 'required|string|in:admin,salon,frizer,klijent',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Update a user.
     */
    public function updateUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:255',
            'role' => 'sometimes|string|in:admin,salon,frizer,klijent',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * Delete a user.
     */
    public function deleteUser(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Reset user password.
     */
    public function resetUserPassword(User $user): JsonResponse
    {
        $newPassword = Str::random(10);

        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        // In production, send email with new password
        // For now, we'll return it in response (not recommended for production)

        // Create notification for user
        $this->notificationService->sendPasswordResetNotification($user, $newPassword);

        return response()->json([
            'message' => 'Password reset successfully. New password has been sent to user email.',
        ]);
    }

    /**
     * Send message to user (creates notification).
     */
    public function sendMessageToUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        // Create notification for user
        \App\Models\Notification::create([
            'type' => 'admin_message',
            'title' => 'Poruka od administratora',
            'message' => $validated['message'],
            'recipient_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Message sent successfully',
        ]);
    }

    /**
     * Update a salon.
     */
    public function updateSalon(Request $request, Salon $salon): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'address' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255',
            'status' => 'sometimes|string|in:pending,approved,suspended',
        ]);

        $salon->update($validated);

        return response()->json([
            'message' => 'Salon updated successfully',
            'salon' => new SalonResource($salon->fresh()),
        ]);
    }

    /**
     * Get all user consents with filtering and pagination.
     */
    public function consents(Request $request): JsonResponse
    {
        $query = UserConsent::with('user:id,name,email,role,created_at');

        // Filter by consent type
        if ($request->has('consent_type') && $request->consent_type) {
            $query->where('consent_type', $request->consent_type);
        }

        // Filter by accepted status
        if ($request->has('accepted')) {
            $query->where('accepted', $request->accepted === 'true');
        }

        // Filter by user role
        if ($request->has('role') && $request->role) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('role', $request->role);
            });
        }

        // Search by user name or email
        if ($request->has('search') && $request->search) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        // Date range filter
        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('accepted_at', '>=', $request->from_date);
        }
        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('accepted_at', '<=', $request->to_date);
        }

        $consents = $query->orderBy('accepted_at', 'desc')
            ->paginate($request->get('per_page', 20));

        // Get consent statistics
        $stats = [
            'total' => UserConsent::count(),
            'by_type' => [
                'privacy_policy' => UserConsent::where('consent_type', 'privacy_policy')->where('accepted', true)->count(),
                'contact_communication' => UserConsent::where('consent_type', 'contact_communication')->where('accepted', true)->count(),
                'public_data_display' => UserConsent::where('consent_type', 'public_data_display')->where('accepted', true)->count(),
                'marketing' => UserConsent::where('consent_type', 'marketing')->where('accepted', true)->count(),
            ],
        ];

        return response()->json([
            'consents' => $consents,
            'stats' => $stats,
            'consent_types' => UserConsent::getConsentLabels(),
        ]);
    }

    /**
     * Get consents for a specific user.
     */
    public function userConsents(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $consents = UserConsent::where('user_id', $userId)->get();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => $user->created_at,
            ],
            'consents' => $consents,
            'consent_labels' => UserConsent::getConsentLabels(),
        ]);
    }

    /**
     * Export consents data (GDPR compliance).
     */
    public function exportConsents(Request $request): JsonResponse
    {
        $consents = UserConsent::with('user:id,name,email,role')
            ->orderBy('accepted_at', 'desc')
            ->get()
            ->map(function ($consent) {
                return [
                    'user_id' => $consent->user_id,
                    'user_name' => $consent->user->name ?? 'N/A',
                    'user_email' => $consent->user->email ?? 'N/A',
                    'user_role' => $consent->user->role ?? 'N/A',
                    'consent_type' => $consent->consent_type,
                    'consent_label' => UserConsent::getConsentLabels()[$consent->consent_type] ?? $consent->consent_type,
                    'accepted' => $consent->accepted,
                    'version' => $consent->version,
                    'ip_address' => $consent->ip_address,
                    'user_agent' => $consent->user_agent,
                    'accepted_at' => $consent->accepted_at?->toISOString(),
                    'revoked_at' => $consent->revoked_at?->toISOString(),
                    'created_at' => $consent->created_at->toISOString(),
                ];
            });

        return response()->json([
            'data' => $consents,
            'exported_at' => now()->toISOString(),
            'total_records' => $consents->count(),
        ]);
    }
}
