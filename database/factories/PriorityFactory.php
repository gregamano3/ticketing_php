<?php

namespace Database\Factories;

use App\Models\Priority;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Priority>
 */
class PriorityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'level' => fake()->numberBetween(1, 4),
            'color' => 'warning',
            'response_minutes' => 240,
            'resolution_minutes' => 1440,
        ];
    }
}
