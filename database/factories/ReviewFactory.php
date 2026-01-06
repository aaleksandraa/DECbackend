<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'salon_id' => Salon::factory(),
            'client_id' => User::factory(),
            'client_name' => $this->faker->name(),
            'rating' => $this->faker->numberBetween(1, 5),
            'comment' => $this->faker->paragraph(),
            'date' => $this->faker->date(),
            'is_verified' => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => false,
        ]);
    }

    public function fiveStars(): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => 5,
        ]);
    }

    public function oneStars(): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => 1,
        ]);
    }
}
