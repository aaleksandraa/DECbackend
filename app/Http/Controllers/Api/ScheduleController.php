<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\StoreBreakRequest;
use App\Http\Requests\Schedule\StoreVacationRequest;
use App\Models\Salon;
use App\Models\SalonBreak;
use App\Models\SalonVacation;
use App\Models\Staff;
use App\Models\StaffBreak;
use App\Models\StaffVacation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    /**
     * Get salon breaks.
     */
    public function getSalonBreaks(Salon $salon): JsonResponse
    {
        $breaks = $salon->salonBreaks()->get();

        return response()->json([
            'breaks' => $breaks,
        ]);
    }

    /**
     * Store a new salon break.
     */
    public function storeSalonBreak(StoreBreakRequest $request, Salon $salon): JsonResponse
    {
        $this->authorize('update', $salon);

        $break = $salon->salonBreaks()->create($request->validated());

        return response()->json([
            'message' => 'Break created successfully',
            'break' => $break,
        ], 201);
    }

    /**
     * Update a salon break.
     */
    public function updateSalonBreak(StoreBreakRequest $request, Salon $salon, SalonBreak $break): JsonResponse
    {
        $this->authorize('update', $salon);

        if ($break->salon_id !== $salon->id) {
            abort(404);
        }

        $break->update($request->validated());

        return response()->json([
            'message' => 'Break updated successfully',
            'break' => $break,
        ]);
    }

    /**
     * Delete a salon break.
     */
    public function deleteSalonBreak(Salon $salon, SalonBreak $break): JsonResponse
    {
        $this->authorize('update', $salon);

        if ($break->salon_id !== $salon->id) {
            abort(404);
        }

        $break->delete();

        return response()->json([
            'message' => 'Break deleted successfully',
        ]);
    }

    /**
     * Get salon vacations.
     */
    public function getSalonVacations(Salon $salon): JsonResponse
    {
        $vacations = $salon->salonVacations()->get();

        return response()->json([
            'vacations' => $vacations,
        ]);
    }

    /**
     * Store a new salon vacation.
     */
    public function storeSalonVacation(StoreVacationRequest $request, Salon $salon): JsonResponse
    {
        $this->authorize('update', $salon);

        $vacation = $salon->salonVacations()->create($request->validated());

        return response()->json([
            'message' => 'Vacation created successfully',
            'vacation' => $vacation,
        ], 201);
    }

    /**
     * Update a salon vacation.
     */
    public function updateSalonVacation(StoreVacationRequest $request, Salon $salon, SalonVacation $vacation): JsonResponse
    {
        $this->authorize('update', $salon);

        if ($vacation->salon_id !== $salon->id) {
            abort(404);
        }

        $vacation->update($request->validated());

        return response()->json([
            'message' => 'Vacation updated successfully',
            'vacation' => $vacation,
        ]);
    }

    /**
     * Delete a salon vacation.
     */
    public function deleteSalonVacation(Salon $salon, SalonVacation $vacation): JsonResponse
    {
        $this->authorize('update', $salon);

        if ($vacation->salon_id !== $salon->id) {
            abort(404);
        }

        $vacation->delete();

        return response()->json([
            'message' => 'Vacation deleted successfully',
        ]);
    }

    /**
     * Get staff breaks.
     */
    public function getStaffBreaks(Staff $staff): JsonResponse
    {
        $breaks = $staff->breaks()->get();

        return response()->json([
            'breaks' => $breaks,
        ]);
    }

    /**
     * Store a new staff break.
     */
    public function storeStaffBreak(StoreBreakRequest $request, Staff $staff): JsonResponse
    {
        $this->authorize('updateStaff', [$staff->salon, $staff]);

        $break = $staff->breaks()->create($request->validated());

        return response()->json([
            'message' => 'Break created successfully',
            'break' => $break,
        ], 201);
    }

    /**
     * Update a staff break.
     */
    public function updateStaffBreak(StoreBreakRequest $request, Staff $staff, StaffBreak $break): JsonResponse
    {
        $this->authorize('updateStaff', [$staff->salon, $staff]);

        if ($break->staff_id !== $staff->id) {
            abort(404);
        }

        $break->update($request->validated());

        return response()->json([
            'message' => 'Break updated successfully',
            'break' => $break,
        ]);
    }

    /**
     * Delete a staff break.
     */
    public function deleteStaffBreak(Staff $staff, StaffBreak $break): JsonResponse
    {
        $this->authorize('updateStaff', [$staff->salon, $staff]);

        if ($break->staff_id !== $staff->id) {
            abort(404);
        }

        $break->delete();

        return response()->json([
            'message' => 'Break deleted successfully',
        ]);
    }

    /**
     * Get staff vacations.
     */
    public function getStaffVacations(Staff $staff): JsonResponse
    {
        $vacations = $staff->vacations()->get();

        return response()->json([
            'vacations' => $vacations,
        ]);
    }

    /**
     * Store a new staff vacation.
     */
    public function storeStaffVacation(StoreVacationRequest $request, Staff $staff): JsonResponse
    {
        $this->authorize('updateStaff', [$staff->salon, $staff]);

        $vacation = $staff->vacations()->create($request->validated());

        return response()->json([
            'message' => 'Vacation created successfully',
            'vacation' => $vacation,
        ], 201);
    }

    /**
     * Update a staff vacation.
     */
    public function updateStaffVacation(StoreVacationRequest $request, Staff $staff, StaffVacation $vacation): JsonResponse
    {
        $this->authorize('updateStaff', [$staff->salon, $staff]);

        if ($vacation->staff_id !== $staff->id) {
            abort(404);
        }

        $vacation->update($request->validated());

        return response()->json([
            'message' => 'Vacation updated successfully',
            'vacation' => $vacation,
        ]);
    }

    /**
     * Delete a staff vacation.
     */
    public function deleteStaffVacation(Staff $staff, StaffVacation $vacation): JsonResponse
    {
        $this->authorize('updateStaff', [$staff->salon, $staff]);

        if ($vacation->staff_id !== $staff->id) {
            abort(404);
        }

        $vacation->delete();

        return response()->json([
            'message' => 'Vacation deleted successfully',
        ]);
    }
}
