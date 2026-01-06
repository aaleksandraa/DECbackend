<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LocationController extends Controller
{
    /**
     * Get all active locations (public)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Location::active()->orderBy('name');

            // Filter by entity
            if ($request->filled('entity')) {
                $query->where('entity', $request->entity);
            }

            // Filter by canton
            if ($request->filled('canton')) {
                $query->where('canton', $request->canton);
            }

            // Search by name (with fuzzy matching for Croatian diacritics)
            if ($request->filled('search')) {
                $searchTerm = $request->search;
                $normalizedSearch = $this->normalizeText($searchTerm);

                $query->where(function ($q) use ($searchTerm, $normalizedSearch) {
                    $q->where('name', 'ilike', '%' . $searchTerm . '%')
                      ->orWhereRaw("TRANSLATE(LOWER(name), 'šŠčČćĆžŽđĐ', 'sScCcCzZdD') ILIKE ?", ['%' . $normalizedSearch . '%']);
                });
            }

            $locations = $query->get();

            return response()->json([
                'locations' => $locations,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error loading locations: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            // Return empty array instead of error
            return response()->json([
                'locations' => [],
            ], 200);
        }
    }

    /**
     * Normalize text - remove Croatian diacritics for fuzzy search
     */
    private function normalizeText(string $text): string
    {
        $replacements = [
            'š' => 's', 'Š' => 'S',
            'č' => 'c', 'Č' => 'C',
            'ć' => 'c', 'Ć' => 'C',
            'ž' => 'z', 'Ž' => 'Z',
            'đ' => 'd', 'Đ' => 'D',
        ];

        return strtolower(strtr($text, $replacements));
    }

    /**
     * Get locations grouped by entity/canton
     */
    public function grouped(): JsonResponse
    {
        try {
            $locations = Location::active()->orderBy('name')->get();

            $grouped = [
                'FBiH' => [],
                'RS' => [],
                'BD' => [],
            ];

            foreach ($locations as $location) {
                if ($location->entity === 'FBiH') {
                    $canton = $location->canton ?: 'Ostalo';
                    if (!isset($grouped['FBiH'][$canton])) {
                        $grouped['FBiH'][$canton] = [];
                    }
                    $grouped['FBiH'][$canton][] = $location;
                } elseif ($location->entity === 'RS') {
                    $region = $location->region ?: 'Ostalo';
                    if (!isset($grouped['RS'][$region])) {
                        $grouped['RS'][$region] = [];
                    }
                    $grouped['RS'][$region][] = $location;
                } else {
                    $grouped['BD'][] = $location;
                }
            }

            return response()->json([
                'grouped' => $grouped,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error loading grouped locations: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            // Return empty grouped structure instead of error
            return response()->json([
                'grouped' => [
                    'FBiH' => [],
                    'RS' => [],
                    'BD' => [],
                ],
            ], 200);
        }
    }

    /**
     * Get unique cantons/regions for filtering
     */
    public function cantons(): JsonResponse
    {
        try {
            $cantons = Location::active()
                ->whereNotNull('canton')
                ->distinct()
                ->pluck('canton')
                ->sort()
                ->values();

            $regions = Location::active()
                ->whereNotNull('region')
                ->distinct()
                ->pluck('region')
                ->sort()
                ->values();

            return response()->json([
                'cantons' => $cantons,
                'regions' => $regions,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error loading cantons/regions: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            // Return empty arrays instead of error
            return response()->json([
                'cantons' => [],
                'regions' => [],
            ], 200);
        }
    }

    // ==================== ADMIN ROUTES ====================

    /**
     * Get all locations (admin)
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Location::orderBy('name');

        if ($request->filled('entity')) {
            $query->where('entity', $request->entity);
        }

        if ($request->filled('search')) {
            $query->where('name', 'ilike', '%' . $request->search . '%');
        }

        $locations = $query->paginate(50);

        return response()->json($locations);
    }

    /**
     * Store a new location (admin)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'postal_code' => 'nullable|string|max:10',
            'entity' => 'required|in:FBiH,RS,BD',
            'canton' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'population' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['city_slug'] = Str::slug($validated['name']);

        // Check for duplicate slug
        if (Location::where('city_slug', $validated['city_slug'])->exists()) {
            return response()->json([
                'message' => 'Lokacija sa ovim imenom već postoji',
            ], 422);
        }

        $location = Location::create($validated);

        return response()->json([
            'message' => 'Lokacija uspješno kreirana',
            'location' => $location,
        ], 201);
    }

    /**
     * Show a single location (admin)
     */
    public function show(Location $location): JsonResponse
    {
        return response()->json([
            'location' => $location,
        ]);
    }

    /**
     * Update a location (admin)
     */
    public function update(Request $request, Location $location): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'postal_code' => 'nullable|string|max:10',
            'entity' => 'sometimes|in:FBiH,RS,BD',
            'canton' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'population' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        // Update slug if name changed
        if (isset($validated['name']) && $validated['name'] !== $location->name) {
            $newSlug = Str::slug($validated['name']);
            if (Location::where('city_slug', $newSlug)->where('id', '!=', $location->id)->exists()) {
                return response()->json([
                    'message' => 'Lokacija sa ovim imenom već postoji',
                ], 422);
            }
            $validated['city_slug'] = $newSlug;
        }

        $location->update($validated);

        return response()->json([
            'message' => 'Lokacija uspješno ažurirana',
            'location' => $location,
        ]);
    }

    /**
     * Delete a location (admin)
     */
    public function destroy(Location $location): JsonResponse
    {
        // Check if any salons use this location
        $salonCount = $location->salons()->count();
        if ($salonCount > 0) {
            return response()->json([
                'message' => "Ne možete obrisati lokaciju jer je koristi {$salonCount} salon(a)",
            ], 422);
        }

        $location->delete();

        return response()->json([
            'message' => 'Lokacija uspješno obrisana',
        ]);
    }
}
