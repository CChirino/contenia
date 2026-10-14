<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'posts_per_month' => null,
            'regenerations_per_month' => null,
            'image_refreshes_per_month' => null,
            'networks' => ['instagram', 'linkedin', 'tiktok'],
            'price_label' => null,
        ];
    }
}
