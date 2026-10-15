<?php

namespace Database\Factories;

use App\Models\ReviewRequest;
use App\Models\ReviewWeekComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewWeekComment>
 */
class ReviewWeekCommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'review_request_id' => ReviewRequest::factory(),
            'week_from' => '2026-10-05',
            'comment' => fake()->sentence(),
        ];
    }
}
