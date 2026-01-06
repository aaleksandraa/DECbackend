<?php

namespace Database\Factories;

use App\Models\Favorite;
use App\Models\User;
use App\Models\Salon;
use Illuminate\Database\Eloquent\Factories\Factory;

class FavoriteFactory extends Factory
{
    protected $model = Favorite::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'salon_id' => Salon::factory(),
        ];
    }
}
