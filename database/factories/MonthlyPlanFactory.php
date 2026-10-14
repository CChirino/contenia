<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\MonthlyPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthlyPlan>
 */
class MonthlyPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => fn () => app()->bound('brand') ? app('brand')->id : Brand::factory(),
            'year' => fake()->numberBetween(2026, 2030),
            'month' => fake()->unique()->numberBetween(1, 12),
            'status' => 'draft',
            'generated_at' => null,
        ];
    }
}
