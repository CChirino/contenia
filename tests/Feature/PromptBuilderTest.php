<?php

namespace Tests\Feature;

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
}
