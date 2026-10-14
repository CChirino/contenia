<?php

namespace Database\Factories;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Enums\PostIcon;
use App\Enums\PostStatus;
use App\Models\Brand;
use App\Models\ContentPillar;
use App\Models\MonthlyPlan;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $network = fake()->randomElement(Network::cases());

        return [
            'brand_id' => fn () => app()->bound('brand') ? app('brand')->id : Brand::factory(),
            'monthly_plan_id' => fn (array $attributes) => MonthlyPlan::factory([
                'brand_id' => $attributes['brand_id'],
            ]),
            'content_pillar_id' => fn (array $attributes) => ContentPillar::factory([
                'brand_id' => $attributes['brand_id'],
            ]),
            'scheduled_at' => fake()->dateTimeBetween('now', '+1 month'),
            'network' => $network,
            'format' => fake()->randomElement(PostFormat::allowedFor($network)),
            'hook' => fake()->sentence(),
            'copy' => fake()->paragraph(),
            'hashtags' => ['#sabere', '#educacion'],
            'design_payload' => ['slides' => [['title' => fake()->sentence(), 'body' => fake()->sentence()]]],
            'background_prompt' => fake()->optional()->sentence(),
            'photo_keywords' => fake()->optional()->words(3, true),
            'icon' => fake()->optional()->randomElement(PostIcon::cases()),
            'status' => PostStatus::Drafted,
        ];
    }

    public function rendered(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Rendered,
            'background_source' => 'pollinations',
            'asset_path' => 'assets/'.fake()->numberBetween(1, 9999).'/frame-01.png',
            'rendered_at' => now(),
        ]);
    }
}
