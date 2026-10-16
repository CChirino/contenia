<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostEdit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostEdit>
 */
class PostEditFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'user_id' => User::factory(),
            'field' => 'copy',
            'before' => fake()->sentence(),
            'after' => fake()->sentence(),
            'review_request_id' => null,
        ];
    }
}
