<?php

namespace Tests\Feature;

use App\Enums\GuidelineStatus;
use App\Models\Brand;
use App\Models\BrandGuideline;
use App\Models\BrandProfile;
use App\Models\CalendarEvent;
use App\Services\Planning\PromptBuilder;
use Carbon\CarbonImmutable;
use Database\Seeders\DefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_week_prompt_contains_only_days_in_range(): void
    {
        $this->seed(DefaultsSeeder::class);

        ['user' => $user] = (new PromptBuilder)->forWeek(
            2026, 10,
            CarbonImmutable::create(2026, 10, 5),
            CarbonImmutable::create(2026, 10, 11),
            []
        );

        foreach (range(5, 11) as $day) {
            $this->assertStringContainsString(sprintf('2026-10-%02d', $day), $user);
        }

        $this->assertStringNotContainsString('2026-10-04', $user);
        $this->assertStringNotContainsString('2026-10-12', $user);
        $this->assertStringNotContainsString('2026-10-20', $user);
        $this->assertStringContainsString('semana del 2026-10-05 al 2026-10-11', $user);
    }

    public function test_week_prompt_includes_already_planned_hooks(): void
    {
        $this->seed(DefaultsSeeder::class);

        ['user' => $user] = (new PromptBuilder)->forWeek(
            2026, 10,
            CarbonImmutable::create(2026, 10, 12),
            CarbonImmutable::create(2026, 10, 18),
            [
                ['hook' => 'Cinco señales de que tu colegio necesita un sistema', 'pillar' => 'Tips para docentes', 'network' => 'instagram'],
            ]
        );

        $this->assertStringContainsString('Cinco señales de que tu colegio necesita un sistema', $user);
        $this->assertStringContainsString('instagram / Tips para docentes', $user);
    }

    public function test_week_prompt_marks_no_posting_events(): void
    {
        $this->seed(DefaultsSeeder::class);

        CalendarEvent::factory()->create([
            'date' => '2026-10-07',
            'no_posting' => true,
        ]);

        ['user' => $user] = (new PromptBuilder)->forWeek(
            2026, 10,
            CarbonImmutable::create(2026, 10, 5),
            CarbonImmutable::create(2026, 10, 11),
            []
        );

        $this->assertStringContainsString('2026-10-07', $user);
        $this->assertStringContainsString('[SIN PUBLICACIÓN]', $user);
    }

    public function test_system_prompt_intro_uses_sabere_name_and_description(): void
    {
        $this->seed(DefaultsSeeder::class);

        ['system' => $system] = (new PromptBuilder)->forWeek(
            2026, 10,
            CarbonImmutable::create(2026, 10, 5),
            CarbonImmutable::create(2026, 10, 11),
            []
        );

        $this->assertStringContainsString('community manager de Saberé, plataforma de gestión escolar', $system);
    }

    public function test_system_prompt_intro_uses_active_brand_without_description(): void
    {
        $brand = Brand::factory()->create(['name' => 'Demo', 'slug' => 'demo']);
        $this->actingAsAdminFor($brand);

        ['system' => $system] = (new PromptBuilder)->forWeek(
            2026, 10,
            CarbonImmutable::create(2026, 10, 5),
            CarbonImmutable::create(2026, 10, 11),
            []
        );

        $this->assertStringContainsString('community manager de Demo.', $system);
        $this->assertStringNotContainsString('Saberé', $system);
    }

    public function test_system_prompt_intro_includes_brand_description(): void
    {
        $brand = Brand::factory()->create(['name' => 'Demo', 'slug' => 'demo']);
        $this->actingAsAdminFor($brand);
        BrandProfile::current()->update(['description' => 'Panadería artesanal en Caracas.']);

        ['system' => $system] = (new PromptBuilder)->forWeek(
            2026, 10,
            CarbonImmutable::create(2026, 10, 5),
            CarbonImmutable::create(2026, 10, 11),
            []
        );

        $this->assertStringContainsString('community manager de Demo, panadería artesanal en Caracas.', $system);
    }

    public function test_system_prompt_includes_active_guidelines_ordered_by_weight(): void
    {
        $this->seed(DefaultsSeeder::class);

        BrandGuideline::factory()->create([
            'text' => 'Pauta ligera',
            'status' => GuidelineStatus::Active,
            'weight' => 1,
        ]);
        BrandGuideline::factory()->create([
            'text' => 'Pauta pesada',
            'status' => GuidelineStatus::Active,
            'weight' => 9,
        ]);

        ['system' => $system] = (new PromptBuilder)->forWeek(
            2026, 10,
            CarbonImmutable::create(2026, 10, 5),
            CarbonImmutable::create(2026, 10, 11),
            []
        );

        $this->assertStringContainsString('Pautas aprendidas de esta marca:', $system);
        // Orden por weight desc: la pesada va primero.
        $this->assertLessThan(
            strpos($system, '- Pauta ligera'),
            strpos($system, '- Pauta pesada')
        );
    }

    public function test_system_prompt_excludes_non_active_guidelines(): void
    {
        $this->seed(DefaultsSeeder::class);

        foreach ([
            GuidelineStatus::Pending,
            GuidelineStatus::PendingRetire,
            GuidelineStatus::Retired,
            GuidelineStatus::Discarded,
        ] as $i => $status) {
            BrandGuideline::factory()->create([
                'text' => "Pauta oculta {$i}",
                'status' => $status,
            ]);
        }

        ['system' => $system] = (new PromptBuilder)->forWeek(
            2026, 10,
            CarbonImmutable::create(2026, 10, 5),
            CarbonImmutable::create(2026, 10, 11),
            []
        );

        $this->assertStringNotContainsString('Pautas aprendidas', $system);
        $this->assertStringNotContainsString('Pauta oculta', $system);
    }

    public function test_system_prompt_limits_guidelines_to_fifteen(): void
    {
        $this->seed(DefaultsSeeder::class);

        BrandGuideline::factory()->count(20)->create([
            'status' => GuidelineStatus::Active,
        ]);

        ['system' => $system] = (new PromptBuilder)->forWeek(
            2026, 10,
            CarbonImmutable::create(2026, 10, 5),
            CarbonImmutable::create(2026, 10, 11),
            []
        );

        $block = substr($system, strpos($system, 'Pautas aprendidas'));
        // Cada viñeta va precedida de "\n- " (la primera incluida).
        $this->assertSame(15, substr_count($block, "\n- "));
    }

    public function test_brand_without_guidelines_has_no_block(): void
    {
        $this->seed(DefaultsSeeder::class);

        ['system' => $system] = (new PromptBuilder)->forWeek(
            2026, 10,
            CarbonImmutable::create(2026, 10, 5),
            CarbonImmutable::create(2026, 10, 11),
            []
        );

        $this->assertStringNotContainsString('Pautas aprendidas', $system);
    }
}
