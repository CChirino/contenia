<?php

namespace Tests\Unit;

use App\Models\CalendarEvent;
use App\Models\ContentPillar;
use App\Services\Planning\PlanValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanValidatorTest extends TestCase
{
    use RefreshDatabase;

    private function validPost(array $overrides = []): array
    {
        return array_merge([
            'date' => '2026-10-05',
            'time' => '18:00',
            'network' => 'instagram',
            'pillar' => 'Tips',
            'format' => 'carousel',
            'hook' => 'Un hook',
            'copy' => 'Un copy',
            'hashtags' => ['#Sabere'],
            'design_payload' => ['slides' => [['title' => 'T', 'body' => 'B']]],
            'background_prompt' => 'ilustración de un aula',
        ], $overrides);
    }

    protected function setUp(): void
    {
        parent::setUp();

        ContentPillar::factory()->create(['name' => 'Tips', 'weight' => 100]);
    }

    private function validate(array $posts): array
    {
        return (new PlanValidator)->validate($posts, 2026, 10);
    }

    public function test_valid_post_is_accepted_and_pillar_resolved(): void
    {
        ['valid' => $valid, 'rejected' => $rejected] = $this->validate([$this->validPost()]);

        $this->assertCount(1, $valid);
        $this->assertCount(0, $rejected);
        $this->assertSame(ContentPillar::first()->id, $valid[0]['content_pillar_id']);
    }

    public function test_pillar_match_is_case_insensitive(): void
    {
        ['valid' => $valid] = $this->validate([$this->validPost(['pillar' => 'TIPS'])]);

        $this->assertCount(1, $valid);
    }

    public function test_rejects_format_not_allowed_for_network(): void
    {
        ['rejected' => $rejected] = $this->validate([
            $this->validPost(['network' => 'tiktok', 'format' => 'carousel']),
        ]);

        $this->assertCount(1, $rejected);
        $this->assertStringContainsString('no permitido', $rejected[0]['reason']);
    }

    public function test_rejects_unknown_pillar(): void
    {
        ['rejected' => $rejected] = $this->validate([
            $this->validPost(['pillar' => 'No existe']),
        ]);

        $this->assertCount(1, $rejected);
        $this->assertStringContainsString('pilar inexistente', $rejected[0]['reason']);
    }

    public function test_rejects_date_outside_month(): void
    {
        ['rejected' => $rejected] = $this->validate([
            $this->validPost(['date' => '2026-11-05']),
        ]);

        $this->assertStringContainsString('fuera del mes', $rejected[0]['reason']);
    }

    public function test_rejects_no_posting_date(): void
    {
        CalendarEvent::factory()->create([
            'date' => '2026-10-10',
            'no_posting' => true,
        ]);

        ['rejected' => $rejected] = $this->validate([
            $this->validPost(['date' => '2026-10-10']),
        ]);

        $this->assertStringContainsString('sin publicación', $rejected[0]['reason']);
    }

    public function test_rejects_recurring_no_posting_date_of_other_year(): void
    {
        CalendarEvent::factory()->create([
            'date' => '2020-12-25',
            'recurring' => true,
            'no_posting' => true,
        ]);

        ['rejected' => $rejected] = $this->validate([
            $this->validPost(['date' => '2026-12-25']),
        ]);

        $this->assertStringContainsString('sin publicación', $rejected[0]['reason']);
    }

    public function test_rejects_empty_required_fields(): void
    {
        ['rejected' => $rejected] = $this->validate([
            $this->validPost(['copy' => '', 'hashtags' => []]),
        ]);

        $this->assertStringContainsString('copy', $rejected[0]['reason']);
        $this->assertStringContainsString('hashtags', $rejected[0]['reason']);
    }

    public function test_normalizes_icon_and_photo_keywords(): void
    {
        ['valid' => $valid] = $this->validate([
            $this->validPost(['icon' => 'fingerprint', 'photo_keywords' => 'school hallway']),
        ]);
        $this->assertSame('fingerprint', $valid[0]['icon']);
        $this->assertSame('school hallway', $valid[0]['photo_keywords']);
    }

    public function test_invalid_icon_or_empty_keywords_become_null(): void
    {
        ['valid' => $valid] = $this->validate([
            $this->validPost(['icon' => 'emoji-raro', 'photo_keywords' => '   ']),
        ]);
        $this->assertNull($valid[0]['icon']);
        $this->assertNull($valid[0]['photo_keywords']);
    }
}
