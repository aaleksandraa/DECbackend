<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('+1 day', '+30 days');
        $time = $this->faker->time('H:i');

        return [
            'salon_id' => Salon::factory(),
            'staff_id' => Staff::factory(),
            'service_id' => Service::factory(),
            'client_id' => User::factory(),
            'client_name' => $this->faker->firstName(),
            'client_email' => $this->faker->safeEmail(),
            'client_phone' => $this->faker->phoneNumber(),
            'date' => $date->format('Y-m-d'),
            'time' => $time,
            'duration' => $this->faker->randomElement([30, 45, 60, 90]),
            'status' => 'confirmed',
            'notes' => $this->faker->paragraph(),
            'booking_source' => 'web',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'date' => now()->subDays(5)->format('Y-m-d'),
        ]);
    }

    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => null,
        ]);
    }
}
