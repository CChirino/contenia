<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandProfile>
 */
class BrandProfileFactory extends Factory
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
            'tone_of_voice' => fake()->sentence(),
            'audience' => fake()->sentence(),
            'base_hashtags' => ['#sabere', '#educacion'],
            'cta' => fake()->sentence(),
            'url' => fake()->url(),
            'palette' => ['#1A2A42', '#F8F5F0', '#27C2A5', '#7854D2'],
        ];
    }
}
