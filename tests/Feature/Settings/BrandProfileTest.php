<?php

namespace Tests\Feature\Settings;

use App\Models\BrandProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('settings.brand.edit'))->assertRedirect(route('login'));
        $this->put(route('settings.brand.update'), [])->assertRedirect(route('login'));
    }

    public function test_edit_page_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('settings.brand.edit'))
            ->assertOk();
    }

    public function test_update_persists_brand_profile(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('settings.brand.update'), [
                'tone_of_voice' => 'Cercano y profesional',
                'audience' => 'Docentes de colegios privados',
                'base_hashtags' => ['#Sabere'],
                'cta' => 'Conoce Saberé',
                'url' => 'https://sabere.com',
                'palette' => ['dark' => '#1A2A42'],
            ])
            ->assertRedirect(route('settings.brand.edit'));

        $profile = BrandProfile::current();
        $this->assertSame('Cercano y profesional', $profile->tone_of_voice);
        $this->assertSame(['#Sabere'], $profile->base_hashtags);
    }

    public function test_update_validates_required_fields(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('settings.brand.update'), [
                'tone_of_voice' => '',
                'url' => 'no-es-una-url',
            ])
            ->assertSessionHasErrors(['tone_of_voice', 'audience', 'url', 'cta']);
    }
}
