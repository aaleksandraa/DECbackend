<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Salon;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'salon_id' => Salon::factory(),
            'name' => $this->faker->randomElement(['Haircut', 'Hair Coloring', 'Styling', 'Beard Trim', 'Hair Treatment', 'Shampoo & Blow Dry']),
            'description' => $this->faker->paragraph(),
            'category' => $this->faker->randomElement(['haircut', 'coloring', 'treatment', 'styling']),
            'price' => $this->faker->randomFloat(2, 10, 100),
            'discount_price' => null,
            'duration' => $this->faker->randomElement([30, 45, 60, 90]),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withDiscount(): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_price' => $attributes['price'] * 0.8,
        ]);
    }
}
