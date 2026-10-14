<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\LlmLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LlmLog>
 */
class LlmLogFactory extends Factory
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
            'purpose' => fake()->word(),
            'prompt' => fake()->paragraph(),
            'response' => fake()->paragraph(),
            'input_tokens' => fake()->numberBetween(100, 5000),
            'output_tokens' => fake()->numberBetween(100, 5000),
            'model' => 'claude-sonnet-4-5',
            'duration_ms' => fake()->numberBetween(500, 30000),
        ];
    }
}
