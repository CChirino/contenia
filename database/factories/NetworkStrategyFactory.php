<?php

namespace Database\Factories;

use App\Enums\Network;
use App\Models\NetworkStrategy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NetworkStrategy>
 */
class NetworkStrategyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'network' => fake()->unique()->randomElement(Network::cases()),
            'posts_per_week' => fake()->numberBetween(1, 7),
            'preferred_formats' => ['image'],
            'focus' => fake()->sentence(),
        ];
    }
}
