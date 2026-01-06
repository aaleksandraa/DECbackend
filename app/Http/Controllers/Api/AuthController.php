<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Models\UserConsent;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $rules = [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => ['required', 'string', 'confirmed', new \App\Rules\StrongPassword()],
                'phone' => 'nullable|string|max:50',
                'role' => 'required|string|in:salon,frizer,klijent',
                'accept_privacy_policy' => 'required|boolean',
                'accept_contact_communication' => 'required|boolean',
            ];

            // Za salone i frizere - dodatni pristanak za javni prikaz podataka
            if (in_array($request->role, ['salon', 'frizer'])) {
                $rules['accept_public_data_display'] = 'required|boolean';
            }

            $messages = [
                'name.required' => 'Ime i prezime je obavezno.',
                'name.max' => 'Ime i prezime ne smije biti duže od 255 karaktera.',
                'email.required' => 'Email adresa je obavezna.',
                'email.email' => 'Email adresa nije validna.',
                'email.unique' => 'Ovaj email je već registrovan. Pokušajte se prijaviti ili koristite drugi email.',
                'password.required' => 'Lozinka je obavezna.',
                'password.min' => 'Lozinka mora imati najmanje 6 karaktera.',
                'password.confirmed' => 'Lozinke se ne poklapaju.',
                'phone.max' => 'Broj telefona ne smije biti duži od 50 karaktera.',
                'role.required' => 'Tip korisnika je obavezan.',
                'role.in' => 'Nevažeći tip korisnika.',
                'accept_privacy_policy.required' => 'Morate prihvatiti pravila privatnosti.',
                'accept_contact_communication.required' => 'Morate pristati na kontakt komunikaciju.',
                'accept_public_data_display.required' => 'Morate pristati na javni prikaz podataka.',
            ];

            $validated = $request->validate($rules, $messages);

            // Dodatna provjera da su pristanci zaista true
            if (!$request->accept_privacy_policy) {
                return response()->json([
                    'success' => false,
                    'message' => 'Morate prihvatiti pravila privatnosti.',
                    'errors' => ['accept_privacy_policy' => ['Morate prihvatiti pravila privatnosti.']]
                ], 422);
            }

            if (!$request->accept_contact_communication) {
                return response()->json([
                    'success' => false,
                    'message' => 'Morate pristati na kontakt komunikaciju.',
                    'errors' => ['accept_contact_communication' => ['Morate pristati na kontakt komunikaciju.']]
                ], 422);
            }

            if (in_array($request->role, ['salon', 'frizer']) && !$request->accept_public_data_display) {
                return response()->json([
                    'success' => false,
                    'message' => 'Morate pristati na javni prikaz podataka.',
                    'errors' => ['accept_public_data_display' => ['Morate pristati na javni prikaz podataka.']]
                ], 422);
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'role' => $request->role,
                'password' => Hash::make($request->password),
            ]);

            // Snimi pristanke
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();

            // Pravila privatnosti - obavezno za sve
            UserConsent::recordConsent(
                $user->id,
                UserConsent::TYPE_PRIVACY_POLICY,
                true,
                $ipAddress,
                $userAgent
            );

            // Kontakt komunikacija - obavezno za sve
            UserConsent::recordConsent(
                $user->id,
                UserConsent::TYPE_CONTACT_COMMUNICATION,
                true,
                $ipAddress,
                $userAgent
            );

            // Javni prikaz podataka - samo za salone i frizere
            if (in_array($request->role, ['salon', 'frizer'])) {
                UserConsent::recordConsent(
                    $user->id,
                    UserConsent::TYPE_PUBLIC_DATA_DISPLAY,
                    true,
                    $ipAddress,
                    $userAgent
                );
            }

            // Pošalji verifikacijski email
            event(new Registered($user));

            // NE ulogujemo korisnika - mora prvo verificirati email
            // Auth::login($user);

            return response()->json([
                'success' => true,
                'message' => 'Registracija uspješna! Provjerite email za potvrdu.',
                'email_verification_required' => true,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Laravel će automatski vratiti 422 sa greškama, ali za svaki slučaj
            return response()->json([
                'success' => false,
                'message' => 'Validacija nije uspjela.',
                'error_code' => 'VALIDATION_ERROR',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // Log unexpected errors with full details
            \Log::error('Registration error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => [
                    'name' => $request->name ?? 'N/A',
                    'email' => $request->email ?? 'N/A',
                    'role' => $request->role ?? 'N/A',
                ]
            ]);

            // In development, show detailed error
            $message = 'Greška pri registraciji. Molimo pokušajte ponovo.';
            $debug = null;

            if (config('app.debug')) {
                $debug = [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'error_code' => 'SERVER_ERROR',
                'debug' => $debug
            ], 500);
        }
    }

    /**
     * Resend email verification link.
     * Can be called by authenticated user or with email parameter for unauthenticated users.
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        // If user is authenticated, use their account
        if ($request->user()) {
            $user = $request->user();
        } else {
            // For unauthenticated users, require email parameter
            $request->validate([
                'email' => 'required|email|exists:users,email'
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'message' => 'Korisnik nije pronađen.'
                ], 404);
            }
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email je već potvrđen.'
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verifikacijski email je ponovo poslan.'
        ]);
    }

    /**
     * Verify email address.
     */
    public function verifyEmail(Request $request, $id, $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'message' => 'Nevažeći verifikacijski link.'
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email je već potvrđen.',
                'already_verified' => true
            ]);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'message' => 'Email uspješno potvrđen!',
            'verified' => true
        ]);
    }

    /**
     * Login a user.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Attempt with remember=true to ensure session persistence
        if (!Auth::attempt($request->only('email', 'password'), true)) {
            return response()->json(['message' => 'Pogrešni podaci.'], 401);
        }

        $user = Auth::user();

        // Check if email is verified
        if (!$user->hasVerifiedEmail()) {
            Auth::logout();
            return response()->json([
                'message' => 'Molimo potvrdite vašu email adresu prije prijave.',
                'email_not_verified' => true,
                'email' => $user->email
            ], 403);
        }

        // Regenerate session if available (stateful requests)
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // Load related data based on user role with status for pending check
        if ($user->role === 'salon') {
            $user->load('ownedSalon');
            $user->salon = $user->ownedSalon ? [
                'id' => $user->ownedSalon->id,
                'name' => $user->ownedSalon->name,
                'address' => $user->ownedSalon->address,
                'city' => $user->ownedSalon->city,
                'status' => $user->ownedSalon->status,
            ] : null;
        } elseif ($user->role === 'frizer') {
            $user->load('staffProfile.salon');
            if ($user->staffProfile) {
                $user->salon = [
                    'id' => $user->staffProfile->salon->id,
                    'name' => $user->staffProfile->salon->name,
                    'address' => $user->staffProfile->salon->address,
                    'city' => $user->staffProfile->salon->city,
                    'status' => $user->staffProfile->salon->status,
                ];
            }
        }

        return response()->json([
            'user' => $user,
            'message' => 'Login successful'
        ]);
    }

    /**
     * Logout a user.
     */
    public function logout(Request $request)
{
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->json(['message' => 'Odjavljen']);
}

    /**
     * Get the authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        // Load related data based on user role
        if ($user->role === 'salon') {
            $user->load('ownedSalon');
            // Set salon property for frontend compatibility with status for pending check
            $user->salon = $user->ownedSalon ? [
                'id' => $user->ownedSalon->id,
                'name' => $user->ownedSalon->name,
                'address' => $user->ownedSalon->address,
                'city' => $user->ownedSalon->city,
                'status' => $user->ownedSalon->status,
            ] : null;
        } elseif ($user->role === 'frizer') {
            $user->load('staffProfile.salon');
            if ($user->staffProfile) {
                $user->salon = [
                    'id' => $user->staffProfile->salon->id,
                    'name' => $user->staffProfile->salon->name,
                    'address' => $user->staffProfile->salon->address,
                    'city' => $user->staffProfile->salon->city,
                    'status' => $user->staffProfile->salon->status,
                ];
                // staffProfile already includes avatar_url via accessor
            }
        }

        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * Update the user's profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'avatar' => 'nullable',
        ]);

        // Handle avatar upload if it's a file
        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $path;
        } elseif (isset($validated['avatar']) && is_string($validated['avatar'])) {
            // If avatar is a string URL, keep it as is or remove from validated if empty
            if (empty($validated['avatar'])) {
                unset($validated['avatar']);
            }
        }

        // Remove null/empty values but keep empty strings for clearing fields
        $validated = array_filter($validated, fn($value) => $value !== null);

        if (!empty($validated)) {
            $user->update($validated);
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Change the user's password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match your current password.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Send password reset link.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        // Uvijek vraćamo isti odgovor - ne otkrivamo postoji li korisnik
        if (!$user) {
            return response()->json([
                'message' => 'Ako korisnik sa ovom email adresom postoji, link za resetovanje lozinke je poslan.'
            ]);
        }

        // Generiši token
        $token = Str::random(64);

        // Obriši stare tokene za ovog korisnika
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Sačuvaj novi token
        DB::table('password_reset_tokens')->insert([
            'email' => $request->email,
            'token' => Hash::make($token),
            'created_at' => now()
        ]);

        // Pošalji notifikaciju
        $user->notify(new ResetPasswordNotification($token));

        return response()->json([
            'message' => 'Ako korisnik sa ovom email adresom postoji, link za resetovanje lozinke je poslan.'
        ]);
    }

    /**
     * Reset password with token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed'
        ]);

        // Pronađi token
        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'Nevažeći ili istekli link za resetovanje lozinke.',
                'errors' => ['token' => ['Nevažeći ili istekli link za resetovanje lozinke.']]
            ], 422);
        }

        // Provjeri da li je token stariji od 60 minuta
        $createdAt = \Carbon\Carbon::parse($record->created_at);
        if ($createdAt->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'Link za resetovanje lozinke je istekao. Molimo zatražite novi.',
                'errors' => ['token' => ['Link za resetovanje lozinke je istekao.']]
            ], 422);
        }

        // Provjeri token
        if (!Hash::check($request->token, $record->token)) {
            return response()->json([
                'message' => 'Nevažeći link za resetovanje lozinke.',
                'errors' => ['token' => ['Nevažeći link za resetovanje lozinke.']]
            ], 422);
        }

        // Pronađi korisnika i ažuriraj lozinku
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Korisnik nije pronađen.',
                'errors' => ['email' => ['Korisnik nije pronađen.']]
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // Obriši iskorišteni token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Lozinka je uspješno resetovana! Sada se možete prijaviti.'
        ]);
    }
}
