<?php

namespace Database\Factories;

use App\Models\Staff;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffFactory extends Factory
{
    protected $model = Staff::class;

    public function definition(): array
    {
        return [
            'salon_id' => Salon::factory(),
            'user_id' => User::factory(),
            'name' => $this->faker->firstName() . ' ' . $this->faker->lastName(),
            'role' => $this->faker->randomElement(['Hairdresser', 'Barber', 'Stylist', 'Colorist']),
            'bio' => $this->faker->paragraph(),
            'avatar' => null,
            'working_hours' => [
                'monday' => ['start' => '08:00', 'end' => '18:00', 'is_working' => true],
                'tuesday' => ['start' => '08:00', 'end' => '18:00', 'is_working' => true],
                'wednesday' => ['start' => '08:00', 'end' => '18:00', 'is_working' => true],
                'thursday' => ['start' => '08:00', 'end' => '18:00', 'is_working' => true],
                'friday' => ['start' => '08:00', 'end' => '18:00', 'is_working' => true],
                'saturday' => ['start' => '09:00', 'end' => '17:00', 'is_working' => true],
                'sunday' => ['start' => '00:00', 'end' => '00:00', 'is_working' => false],
            ],
            'specialties' => ['haircut', 'styling'],
            'rating' => $this->faker->randomFloat(1, 3, 5),
            'review_count' => $this->faker->numberBetween(0, 50),
            'is_active' => true,
            'auto_confirm' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
