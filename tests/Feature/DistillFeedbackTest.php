<?php

namespace Tests\Feature;

use App\Enums\GuidelineSource;
use App\Enums\GuidelineStatus;
use App\Jobs\DistillFeedback;
use App\Models\Brand;
use App\Models\BrandGuideline;
use App\Models\LlmLog;
use App\Models\MonthlyPlan;
use App\Models\Post;
use App\Models\PostEdit;
use App\Models\ReviewRequest;
use App\Services\Llm\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class DistillFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function runJob(Brand $brand, string $from = '2026-10-01', string $to = '2026-10-31'): void
    {
        (new DistillFeedback($brand, $from, $to))->handle(app(LlmClient::class));
    }

    /**
     * Revisión enviada con evidencia: rechazo con comentario, comentario de
     * semana y comentario general.
     */
    private function reviewWithEvidence(Brand $brand, array $state = []): ReviewRequest
    {
        $post = Post::factory()->rendered()->create([
            'brand_id' => $brand->id,
            'monthly_plan_id' => MonthlyPlan::factory()->create(['brand_id' => $brand->id]),
        ]);

        $review = ReviewRequest::factory()->create([
            'brand_id' => $brand->id,
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-31',
            'submitted_at' => now(),
            'general_comment' => 'En general más sobrio, por favor.',
            ...$state,
        ]);

        $review->items()->create([
            'post_id' => $post->id,
            'week_from' => '2026-10-05',
            'decision' => 'rejected',
            'comment' => 'El tono es demasiado infantil',
        ]);
        $review->weekComments()->create([
            'week_from' => '2026-10-05',
            'comment' => 'Faltan precios',
        ]);

        return $review;
    }

    public function test_submitting_a_review_enqueues_distill_feedback(): void
    {
        Queue::fake();

        $brand = Brand::factory()->create(['requires_client_approval' => true]);
        $plan = MonthlyPlan::factory()->create(['brand_id' => $brand->id]);

        $review = new ReviewRequest([
            'brand_id' => $brand->id,
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-31',
            'expires_at' => now()->addDays(14),
            'sent_to' => ['cliente@test.com'],
        ]);
        $token = Str::random(40);
        $review->token_hash = hash('sha256', $token);
        $review->save();

        $post = Post::factory()->rendered()->create([
            'brand_id' => $brand->id,
            'monthly_plan_id' => $plan->id,
        ]);
        $review->items()->create([
            'post_id' => $post->id,
            'week_from' => '2026-10-05',
            'decision' => 'rejected',
            'comment' => 'no me gusta',
        ]);

        $this->post("/review/{$token}/submit")->assertRedirect();

        Queue::assertPushed(DistillFeedback::class, fn (DistillFeedback $job) => $job->brand->is($brand)
            && Carbon::parse($job->from)->isSameDay('2026-10-01')
            && Carbon::parse($job->to)->isSameDay('2026-10-31'));
    }

    public function test_distills_client_evidence_into_guidelines(): void
    {
        $brand = Brand::factory()->create();
        app()->instance('brand', $brand);

        $active = BrandGuideline::factory()->active()->create([
            'brand_id' => $brand->id,
            'weight' => 3,
            'evidence_count' => 2,
        ]);
        $toRetire = BrandGuideline::factory()->active()->create(['brand_id' => $brand->id]);

        $this->reviewWithEvidence($brand);

        app(LlmClient::class)->withResponse([
            'new' => [['text' => 'Evita exclamaciones en los hooks.']],
            'reinforce' => [$active->id],
            'retire' => [['id' => $toRetire->id, 'reason' => 'Contradicha por el feedback']],
        ]);

        $this->runJob($brand);

        // Nueva pauta: pending; la evidencia es toda del cliente (sin post_edits).
        $new = BrandGuideline::where('text', 'Evita exclamaciones en los hooks.')->sole();
        $this->assertSame(GuidelineStatus::Pending, $new->status);
        $this->assertSame(GuidelineSource::ClientFeedback, $new->source);

        // Reforzada: +1 evidencia, +1 peso.
        $active->refresh();
        $this->assertSame(3, $active->evidence_count);
        $this->assertSame(4, $active->weight);

        // Retirada: active → pending_retire (a confirmar por el operador).
        $this->assertSame(GuidelineStatus::PendingRetire, $toRetire->fresh()->status);
    }

    public function test_post_edits_make_the_source_ai_distilled(): void
    {
        $brand = Brand::factory()->create();
        app()->instance('brand', $brand);

        $post = Post::factory()->create([
            'brand_id' => $brand->id,
            'monthly_plan_id' => MonthlyPlan::factory()->create(['brand_id' => $brand->id]),
        ]);
        PostEdit::factory()->create([
            'post_id' => $post->id,
            'field' => 'hook',
            'before' => '¡Oferta!',
            'after' => 'Oferta',
            'created_at' => '2026-10-15 10:00:00',
        ]);

        app(LlmClient::class)->withResponse([
            'new' => [['text' => 'Sin signos de exclamación de apertura.']],
            'reinforce' => [],
            'retire' => [],
        ]);

        $this->runJob($brand);

        $this->assertSame(
            GuidelineSource::AiDistilled,
            BrandGuideline::where('text', 'Sin signos de exclamación de apertura.')->sole()->source
        );
    }

    public function test_empty_evidence_does_not_call_the_llm(): void
    {
        $brand = Brand::factory()->create();
        app()->instance('brand', $brand);

        $this->runJob($brand);

        $this->assertSame(0, LlmLog::withoutGlobalScopes()->count());
        $this->assertSame(0, BrandGuideline::count());
    }

    public function test_retiring_a_pending_guideline_discards_it(): void
    {
        $brand = Brand::factory()->create();
        app()->instance('brand', $brand);

        $pending = BrandGuideline::factory()->create(['brand_id' => $brand->id]);
        $this->reviewWithEvidence($brand);

        app(LlmClient::class)->withResponse([
            'new' => [],
            'reinforce' => [],
            'retire' => [['id' => $pending->id, 'reason' => 'Duplicada']],
        ]);

        $this->runJob($brand);

        $this->assertSame(GuidelineStatus::Discarded, $pending->fresh()->status);
    }

    public function test_does_not_touch_guidelines_of_other_brands(): void
    {
        $brand = Brand::factory()->create();
        $other = Brand::factory()->create();

        $foreign = BrandGuideline::factory()->active()->create([
            'brand_id' => $other->id,
            'weight' => 5,
            'evidence_count' => 4,
        ]);

        app()->instance('brand', $brand);
        $this->reviewWithEvidence($brand);

        // El LLM (o un bug) intenta tocar la pauta de otra marca: se ignora.
        app(LlmClient::class)->withResponse([
            'new' => [],
            'reinforce' => [$foreign->id],
            'retire' => [['id' => $foreign->id, 'reason' => 'x']],
        ]);

        $this->runJob($brand);

        $foreign->refresh();
        $this->assertSame(GuidelineStatus::Active, $foreign->status);
        $this->assertSame(5, $foreign->weight);
        $this->assertSame(4, $foreign->evidence_count);
    }
}
