<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandContact>
 */
class BrandContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'brand_id' => fn () => app()->bound('brand') ? app('brand')->id : Brand::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'whatsapp' => null,
        ];
    }
}
