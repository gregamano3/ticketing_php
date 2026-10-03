<?php

namespace Database\Factories;

use App\Models\KbCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KbCategory>
 */
class KbCategoryFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => ucfirst(fake()->unique()->words(2, true))];
    }
}
