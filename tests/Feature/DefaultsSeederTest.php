<?php

namespace Tests\Feature;

use App\Models\BrandProfile;
use App\Models\ContentPillar;
use App\Models\NetworkStrategy;
use Database\Seeders\DefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_three_network_strategies(): void
    {
        $this->seed(DefaultsSeeder::class);

        $this->assertCount(3, NetworkStrategy::all());
        $this->assertEqualsCanonicalizing(
            ['instagram', 'linkedin', 'tiktok'],
            NetworkStrategy::all()->pluck('network')->map->value->all()
        );
    }

    public function test_pillar_weights_sum_to_one_hundred(): void
    {
        $this->seed(DefaultsSeeder::class);

        $this->assertCount(4, ContentPillar::all());
        $this->assertSame(100, (int) ContentPillar::sum('weight'));
    }

    public function test_seeds_brand_profile_singleton(): void
    {
        $this->seed(DefaultsSeeder::class);

        $this->assertCount(1, BrandProfile::all());
        $this->assertNotEmpty(BrandProfile::current()->tone_of_voice);
    }
}
