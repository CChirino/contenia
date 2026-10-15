<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\ReviewRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewRequest>
 */
class ReviewRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'brand_id' => fn () => app()->bound('brand') ? app('brand')->id : Brand::factory(),
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-31',
            'token_hash' => hash('sha256', fake()->unique()->password(40)),
            'expires_at' => now()->addDays(14),
            'sent_to' => [fake()->safeEmail()],
            'created_by' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(['submitted_at' => now()]);
    }

    public function revoked(): static
    {
        return $this->state(['revoked_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
