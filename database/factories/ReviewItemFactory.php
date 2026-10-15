<?php

namespace Database\Factories;

use App\Enums\ReviewDecision;
use App\Models\Post;
use App\Models\ReviewItem;
use App\Models\ReviewRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewItem>
 */
class ReviewItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'review_request_id' => ReviewRequest::factory(),
            'post_id' => Post::factory(),
            'decision' => ReviewDecision::Pending,
            'week_from' => '2026-10-05',
        ];
    }
}
