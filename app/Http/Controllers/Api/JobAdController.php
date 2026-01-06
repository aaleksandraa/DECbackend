<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobAd;
use App\Models\Salon;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class JobAdController extends Controller
{
    /**
     * Get all active job ads for public display.
     */
    public function index(Request $request): JsonResponse
    {
        $query = JobAd::active()
            ->with(['salon:id,name,slug,city'])
            ->orderBy('created_at', 'desc');

        // Filter by city
        if ($request->has('city') && $request->city) {
            $query->where('city', 'ILIKE', '%' . $request->city . '%');
        }

        // Filter by gender
        if ($request->has('gender') && $request->gender && $request->gender !== 'any') {
            $query->where(function ($q) use ($request) {
                $q->where('gender_requirement', $request->gender)
                  ->orWhere('gender_requirement', 'any');
            });
        }

        // Search by position or company
        if ($request->has('q') && $request->q) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('position_title', 'ILIKE', '%' . $search . '%')
                  ->orWhere('company_name', 'ILIKE', '%' . $search . '%')
                  ->orWhere('description', 'ILIKE', '%' . $search . '%');
            });
        }

        $perPage = $request->get('per_page', 12);
        $jobAds = $query->paginate($perPage);

        return response()->json([
            'job_ads' => $jobAds->items(),
            'meta' => [
                'current_page' => $jobAds->currentPage(),
                'last_page' => $jobAds->lastPage(),
                'per_page' => $jobAds->perPage(),
                'total' => $jobAds->total(),
            ],
        ]);
    }

    /**
     * Get a single job ad by ID.
     */
    public function show(int $id): JsonResponse
    {
        $jobAd = JobAd::with(['salon:id,name,slug,city,address'])
            ->findOrFail($id);

        if (!$jobAd->is_active && !Auth::check()) {
            abort(404);
        }

        return response()->json([
            'job_ad' => $jobAd,
        ]);
    }

    /**
     * Get all job ads for admin management.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = JobAd::with(['salon:id,name,slug', 'creator:id,name,email'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->whereRaw('is_active = true');
            } elseif ($request->status === 'inactive') {
                $query->whereRaw('is_active = false');
            } elseif ($request->status === 'expired') {
                $query->where('deadline', '<', now()->toDateString());
            }
        }

        $perPage = $request->get('per_page', 20);
        $jobAds = $query->paginate($perPage);

        return response()->json([
            'job_ads' => $jobAds->items(),
            'meta' => [
                'current_page' => $jobAds->currentPage(),
                'last_page' => $jobAds->lastPage(),
                'per_page' => $jobAds->perPage(),
                'total' => $jobAds->total(),
            ],
            'allow_owner_posting' => SystemSetting::get('allow_owner_job_ads', false),
        ]);
    }

    /**
     * Create a new job ad (admin).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'position_title' => 'required|string|max:255',
            'description' => 'required|string',
            'gender_requirement' => 'required|in:male,female,any',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:255',
            'deadline' => 'nullable|date|after:today',
            'salon_id' => 'nullable|exists:salons,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $jobAd = JobAd::create([
            ...$validator->validated(),
            'created_by' => Auth::id(),
            'is_active' => $request->get('is_active', true),
        ]);

        return response()->json([
            'message' => 'Oglas za posao je uspješno kreiran.',
            'job_ad' => $jobAd->load(['salon:id,name,slug']),
        ], 201);
    }

    /**
     * Update a job ad (admin).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $jobAd = JobAd::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'company_name' => 'sometimes|required|string|max:255',
            'position_title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'gender_requirement' => 'sometimes|required|in:male,female,any',
            'contact_email' => 'sometimes|required|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:255',
            'deadline' => 'nullable|date',
            'salon_id' => 'nullable|exists:salons,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $jobAd->update($validator->validated());

        return response()->json([
            'message' => 'Oglas za posao je uspješno ažuriran.',
            'job_ad' => $jobAd->load(['salon:id,name,slug']),
        ]);
    }

    /**
     * Delete a job ad (admin).
     */
    public function destroy(int $id): JsonResponse
    {
        $jobAd = JobAd::findOrFail($id);
        $jobAd->delete();

        return response()->json([
            'message' => 'Oglas za posao je uspješno obrisan.',
        ]);
    }

    /**
     * Toggle job ad active status.
     */
    public function toggleActive(int $id): JsonResponse
    {
        $jobAd = JobAd::findOrFail($id);
        $jobAd->is_active = !$jobAd->is_active;
        $jobAd->save();

        return response()->json([
            'message' => $jobAd->is_active ? 'Oglas je aktiviran.' : 'Oglas je deaktiviran.',
            'job_ad' => $jobAd,
        ]);
    }

    /**
     * Update setting to allow/disallow salon owners to post job ads.
     */
    public function updateOwnerPostingSetting(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'allow_owner_posting' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        SystemSetting::set('allow_owner_job_ads', $request->allow_owner_posting);

        return response()->json([
            'message' => $request->allow_owner_posting
                ? 'Vlasnici salona sada mogu objavljivati oglase.'
                : 'Vlasnicima salona je onemogućeno objavljivanje oglasa.',
            'allow_owner_posting' => $request->allow_owner_posting,
        ]);
    }

    /**
     * Get job ads for the current salon owner.
     */
    public function ownerIndex(Request $request): JsonResponse
    {
        // Check if owner posting is allowed
        $allowOwnerPosting = SystemSetting::get('allow_owner_job_ads', false);

        $user = Auth::user();
        $salon = Salon::where('owner_id', $user->id)->first();

        if (!$salon) {
            return response()->json([
                'message' => 'Nemate registrirani salon.',
            ], 403);
        }

        $query = JobAd::where('salon_id', $salon->id)
            ->orderBy('created_at', 'desc');

        $jobAds = $query->get();

        return response()->json([
            'job_ads' => $jobAds,
            'allow_posting' => $allowOwnerPosting,
            'salon' => [
                'id' => $salon->id,
                'name' => $salon->name,
                'city' => $salon->city,
                'email' => $salon->email,
                'phone' => $salon->phone,
            ],
        ]);
    }

    /**
     * Create a job ad for the current salon owner.
     */
    public function ownerStore(Request $request): JsonResponse
    {
        // Check if owner posting is allowed
        $allowOwnerPosting = SystemSetting::get('allow_owner_job_ads', false);
        if (!$allowOwnerPosting) {
            return response()->json([
                'message' => 'Objavljivanje oglasa za posao nije omogućeno za vlasnike salona.',
            ], 403);
        }

        $user = Auth::user();
        $salon = Salon::where('owner_id', $user->id)->first();

        if (!$salon) {
            return response()->json([
                'message' => 'Nemate registrirani salon.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'position_title' => 'required|string|max:255',
            'description' => 'required|string',
            'gender_requirement' => 'required|in:male,female,any',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'deadline' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $jobAd = JobAd::create([
            'salon_id' => $salon->id,
            'created_by' => $user->id,
            'company_name' => $salon->name,
            'position_title' => $request->position_title,
            'description' => $request->description,
            'gender_requirement' => $request->gender_requirement,
            'contact_email' => $request->contact_email ?: $salon->email,
            'contact_phone' => $request->contact_phone ?: $salon->phone,
            'city' => $salon->city,
            'deadline' => $request->deadline,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Oglas za posao je uspješno kreiran.',
            'job_ad' => $jobAd,
        ], 201);
    }

    /**
     * Update a job ad for the current salon owner.
     */
    public function ownerUpdate(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $salon = Salon::where('owner_id', $user->id)->first();

        if (!$salon) {
            return response()->json([
                'message' => 'Nemate registrirani salon.',
            ], 403);
        }

        $jobAd = JobAd::where('id', $id)
            ->where('salon_id', $salon->id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'position_title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'gender_requirement' => 'sometimes|required|in:male,female,any',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'deadline' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $jobAd->update($validator->validated());

        return response()->json([
            'message' => 'Oglas za posao je uspješno ažuriran.',
            'job_ad' => $jobAd,
        ]);
    }

    /**
     * Delete a job ad for the current salon owner.
     */
    public function ownerDestroy(int $id): JsonResponse
    {
        $user = Auth::user();
        $salon = Salon::where('owner_id', $user->id)->first();

        if (!$salon) {
            return response()->json([
                'message' => 'Nemate registrirani salon.',
            ], 403);
        }

        $jobAd = JobAd::where('id', $id)
            ->where('salon_id', $salon->id)
            ->firstOrFail();

        $jobAd->delete();

        return response()->json([
            'message' => 'Oglas za posao je uspješno obrisan.',
        ]);
    }
}
