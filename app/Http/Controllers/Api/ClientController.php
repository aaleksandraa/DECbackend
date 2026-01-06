<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    /**
     * Get clients for salon owner or staff member
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Get salon_id based on user role
        $salonId = null;
        if ($user->role === 'salon') {
            // Salon owner - get salon via ownedSalon relationship
            $salon = \App\Models\Salon::where('owner_id', $user->id)->first();
            if ($salon) {
                $salonId = $salon->id;
            }
        } elseif ($user->role === 'frizer') {
            // Staff member - get salon via staff profile
            $staff = \App\Models\Staff::where('user_id', $user->id)->first();
            if ($staff) {
                $salonId = $staff->salon_id;
            }
        }

        if (!$salonId) {
            return response()->json(['message' => 'Salon not found'], 404);
        }

        // Get search and filter parameters
        $search = $request->input('search', '');
        $perPage = $request->input('per_page', 20);
        $sortBy = $request->input('sort_by', 'last_visit');
        $sortDirection = $request->input('sort_direction', 'desc');

        // Get unique clients who had appointments at this salon
        $query = User::select('users.*')
            ->join('appointments', 'users.id', '=', 'appointments.client_id')
            ->where('appointments.salon_id', $salonId)
            ->where('appointments.status', '!=', 'cancelled')
            ->groupBy('users.id');

        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('users.name', 'ILIKE', "%{$search}%")
                  ->orWhere('users.email', 'ILIKE', "%{$search}%")
                  ->orWhere('users.phone', 'ILIKE', "%{$search}%");
            });
        }

        // Get clients with appointment stats
        $clients = $query->get()->map(function($client) use ($salonId) {
            $appointments = Appointment::where('client_id', $client->id)
                ->where('salon_id', $salonId)
                ->where('status', '!=', 'cancelled')
                ->get();

            $totalAppointments = $appointments->count();
            $completedAppointments = $appointments->where('status', 'completed')->count();
            $lastAppointment = $appointments->sortByDesc('date')->first();
            $totalSpent = $appointments->where('status', 'completed')->sum('total_price');

            return [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'avatar' => $client->avatar,
                'total_appointments' => $totalAppointments,
                'completed_appointments' => $completedAppointments,
                'last_visit' => $lastAppointment ? $lastAppointment->date : null,
                'total_spent' => $totalSpent,
                'member_since' => $appointments->min('created_at'),
            ];
        });

        // Sort clients
        $clients = $clients->sortBy(function($client) use ($sortBy) {
            return match($sortBy) {
                'name' => $client['name'],
                'total_appointments' => -$client['total_appointments'],
                'total_spent' => -$client['total_spent'],
                'last_visit' => $client['last_visit'] ? -strtotime($client['last_visit']) : 0,
                default => $client['last_visit'] ? -strtotime($client['last_visit']) : 0,
            };
        });

        if ($sortDirection === 'asc') {
            $clients = $clients->reverse();
        }

        // Paginate manually
        $page = $request->input('page', 1);
        $total = $clients->count();
        $clients = $clients->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'clients' => $clients,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage),
        ]);
    }

    /**
     * Get client details with appointment history
     */
    public function show(Request $request, int $clientId): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Get salon_id based on user role
        $salonId = null;
        if ($user->role === 'salon') {
            // Salon owner - get salon via ownedSalon relationship
            $salon = \App\Models\Salon::where('owner_id', $user->id)->first();
            if ($salon) {
                $salonId = $salon->id;
            }
        } elseif ($user->role === 'frizer') {
            // Staff member - get salon via staff profile
            $staff = \App\Models\Staff::where('user_id', $user->id)->first();
            if ($staff) {
                $salonId = $staff->salon_id;
            }
        }

        if (!$salonId) {
            return response()->json(['message' => 'Salon not found'], 404);
        }

        $client = User::find($clientId);

        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        // Get appointments for this client at this salon
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
            'appointments' => $appointments->map(function($appointment) {
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
     * Send email to client(s)
     */
    public function sendEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Get salon_id based on user role
        $salonId = null;
        $salonName = '';
        if ($user->role === 'salon') {
            // Salon owner - get salon via ownedSalon relationship
            $salon = \App\Models\Salon::where('owner_id', $user->id)->first();
            if ($salon) {
                $salonId = $salon->id;
                $salonName = $salon->name;
            }
        } elseif ($user->role === 'frizer') {
            // Staff member - get salon via staff profile
            $staff = \App\Models\Staff::where('user_id', $user->id)->first();
            if ($staff) {
                $salon = \App\Models\Salon::find($staff->salon_id);
                $salonId = $staff->salon_id;
                $salonName = $salon ? $salon->name : '';
            }
        }

        if (!$salonId) {
            return response()->json(['message' => 'Salon not found'], 404);
        }

        $validated = $request->validate([
            'client_ids' => 'required|array',
            'client_ids.*' => 'integer|exists:users,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $clientIds = $validated['client_ids'];
        $subject = $validated['subject'];
        $message = $validated['message'];

        // Get clients
        $clients = User::whereIn('id', $clientIds)->get();

        $sentCount = 0;
        $failedCount = 0;

        foreach ($clients as $client) {
            try {
                Mail::raw($message, function ($mail) use ($client, $subject, $salonName) {
                    $mail->to($client->email)
                        ->subject($subject)
                        ->from(config('mail.from.address'), $salonName);
                });
                $sentCount++;
            } catch (\Exception $e) {
                $failedCount++;
                \Log::error('Failed to send email to client: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => "Email poslat na {$sentCount} klijenata",
            'sent' => $sentCount,
            'failed' => $failedCount,
        ]);
    }
}
