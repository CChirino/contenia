<?php

namespace Tests\Feature\Settings;

use App\Enums\Network;
use App\Models\NetworkStrategy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkStrategyTest extends TestCase
{
    use RefreshDatabase;

    private function strategy(Network $network): NetworkStrategy
    {
        return NetworkStrategy::factory()->create(['network' => $network]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('settings.networks.index'))->assertRedirect(route('login'));
        $this->put(route('settings.networks.update'), [])->assertRedirect(route('login'));
    }

    public function test_index_renders(): void
    {
        $this->strategy(Network::Instagram);

        $this->actingAs(User::factory()->create())
            ->get(route('settings.networks.index'))
            ->assertOk();
    }

    public function test_update_persists_strategies(): void
    {
        $strategy = $this->strategy(Network::Instagram);

        $this->actingAs(User::factory()->create())
            ->put(route('settings.networks.update'), [
                'strategies' => [
                    [
                        'id' => $strategy->id,
                        'posts_per_week' => 5,
                        'preferred_formats' => ['carousel'],
                        'focus' => 'Nuevo enfoque',
                    ],
                ],
            ])
            ->assertRedirect(route('settings.networks.index'));

        $strategy->refresh();
        $this->assertSame(5, $strategy->posts_per_week);
        $this->assertSame(['carousel'], $strategy->preferred_formats);
        $this->assertSame('Nuevo enfoque', $strategy->focus);
    }

    public function test_update_rejects_invalid_posts_per_week(): void
    {
        $strategy = $this->strategy(Network::Instagram);

        $this->actingAs(User::factory()->create())
            ->put(route('settings.networks.update'), [
                'strategies' => [
                    [
                        'id' => $strategy->id,
                        'posts_per_week' => 15,
                        'preferred_formats' => ['image'],
                        'focus' => 'Enfoque',
                    ],
                ],
            ])
            ->assertSessionHasErrors('strategies.0.posts_per_week');
    }

    public function test_update_rejects_format_not_allowed_for_network(): void
    {
        $strategy = $this->strategy(Network::LinkedIn);

        $this->actingAs(User::factory()->create())
            ->put(route('settings.networks.update'), [
                'strategies' => [
                    [
                        'id' => $strategy->id,
                        'posts_per_week' => 3,
                        'preferred_formats' => ['video'],
                        'focus' => 'Enfoque',
                    ],
                ],
            ])
            ->assertSessionHasErrors('strategies.0.preferred_formats');
    }
}
