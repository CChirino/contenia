<?php

namespace Database\Factories;

use App\Enums\BrandStatus;
use App\Models\Brand;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 9999),
            'logo_path' => null,
            'palette' => [
                'dark' => '#1A2A42',
                'light' => '#F8F5F0',
                'accent' => '#27C2A5',
                'secondary' => '#7854D2',
            ],
            'website_url' => null,
            'status' => BrandStatus::Active,
            'plan_id' => Plan::factory(),
            'requires_client_approval' => true,
            'onboarding_proposal' => null,
        ];
    }
}
