<?php

namespace Database\Factories;

use App\Enums\GuidelineSource;
use App\Enums\GuidelineStatus;
use App\Models\Brand;
use App\Models\BrandGuideline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandGuideline>
 */
class BrandGuidelineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'brand_id' => fn () => app()->bound('brand') ? app('brand')->id : Brand::factory(),
            'text' => fake()->sentence(),
            'source' => GuidelineSource::AiDistilled,
            'status' => GuidelineStatus::Pending,
            'weight' => 1,
            'evidence_count' => 1,
            'period_from' => null,
            'period_to' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => GuidelineStatus::Active]);
    }

    public function pendingRetire(): static
    {
        return $this->state(['status' => GuidelineStatus::PendingRetire]);
    }
}
