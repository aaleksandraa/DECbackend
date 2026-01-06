<?php

namespace App\Services;

use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StaffService
{
    /**
     * Create a new staff member.
     */
    public function createStaff(Salon $salon, array $data): Staff
    {
        return DB::transaction(function () use ($salon, $data) {
            // Create user account if email is provided
            $userId = null;
            if (isset($data['email']) && isset($data['password'])) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'phone' => $data['phone'] ?? null,
                    'role' => 'frizer',
                    'salon_id' => $salon->id,
                ]);

                $userId = $user->id;
            }

            // Create staff profile
            // Note: Using raw SQL for boolean fields to handle PostgreSQL boolean type correctly
            $staffId = DB::table('staff')->insertGetId([
                'user_id' => $userId,
                'salon_id' => $salon->id,
                'name' => $data['name'],
                'role' => $data['role'],
                'bio' => $data['bio'] ?? null,
                'working_hours' => json_encode($data['working_hours']),
                'specialties' => json_encode($data['specialties'] ?? []),
                'is_active' => true,
                'is_public' => true,
                'accepts_bookings' => true,
                'slug' => \Illuminate\Support\Str::slug($data['name']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $staff = Staff::find($staffId);

            // Assign services
            if (isset($data['service_ids']) && is_array($data['service_ids'])) {
                $staff->services()->sync($data['service_ids']);
            }

            return $staff;
        });
    }

    /**
     * Update an existing staff member.
     */
    public function updateStaff(Staff $staff, array $data): Staff
    {
        return DB::transaction(function () use ($staff, $data) {
            // Update user account if exists and any user-related fields are provided
            if ($staff->user_id && (isset($data['email']) || isset($data['password']) || isset($data['phone']))) {
                $user = User::find($staff->user_id);
                if ($user) {
                    $userData = [];

                    // Update name if provided
                    if (isset($data['name'])) {
                        $userData['name'] = $data['name'];
                    }

                    // Update email if provided and not empty
                    if (isset($data['email']) && !empty(trim($data['email']))) {
                        $userData['email'] = $data['email'];
                    }

                    // Update phone if provided
                    if (isset($data['phone'])) {
                        $userData['phone'] = $data['phone'];
                    }

                    // Update password if provided and not empty
                    if (isset($data['password']) && !empty(trim($data['password']))) {
                        $userData['password'] = Hash::make($data['password']);
                    }

                    if (!empty($userData)) {
                        $user->update($userData);
                    }
                }
            }

            // Update staff profile
            $staffData = array_filter($data, function ($key) {
                return !in_array($key, ['email', 'password', 'phone', 'service_ids']);
            }, ARRAY_FILTER_USE_KEY);

            $staff->update($staffData);

            // Update services
            if (isset($data['service_ids']) && is_array($data['service_ids'])) {
                $staff->services()->sync($data['service_ids']);
            }

            return $staff;
        });
    }

    /**
     * Get staff availability for a date range.
     */
    public function getStaffAvailability(Staff $staff, string $startDate, string $endDate): array
    {
        $start = strtotime($startDate);
        $end = strtotime($endDate);
        $availability = [];

        for ($date = $start; $date <= $end; $date = strtotime('+1 day', $date)) {
            $dateString = date('d.m.Y', $date);
            $dayOfWeek = strtolower(date('l', $date));

            // Check working hours
            $workingHours = $staff->working_hours[$dayOfWeek] ?? null;
            $isWorking = $workingHours && $workingHours['is_working'];

            // Check for vacations
            $isOnVacation = false;
            foreach ($staff->vacations as $vacation) {
                if ($vacation->isActiveFor($dateString)) {
                    $isOnVacation = true;
                    break;
                }
            }

            // Check for salon vacations
            $salon = $staff->salon;
            $isSalonClosed = false;
            foreach ($salon->salonVacations as $vacation) {
                if ($vacation->isActiveFor($dateString)) {
                    $isSalonClosed = true;
                    break;
                }
            }

            $availability[$dateString] = [
                'is_working' => $isWorking,
                'is_on_vacation' => $isOnVacation,
                'is_salon_closed' => $isSalonClosed,
                'is_available' => $isWorking && !$isOnVacation && !$isSalonClosed,
            ];
        }

        return $availability;
    }
}
