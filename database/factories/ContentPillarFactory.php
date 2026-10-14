<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\ContentPillar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentPillar>
 */
class ContentPillarFactory extends Factory
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
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'weight' => fake()->numberBetween(10, 50),
        ];
    }
}
