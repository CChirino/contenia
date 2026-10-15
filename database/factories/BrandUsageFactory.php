<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandUsage>
 */
class BrandUsageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'brand_id' => fn () => app()->bound('brand') ? app('brand')->id : Brand::factory(),
            'year' => 2026,
            'month' => 10,
        ];
    }
}
