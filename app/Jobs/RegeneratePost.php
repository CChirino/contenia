<?php

namespace App\Jobs;

use App\Enums\PostIcon;
use App\Enums\PostStatus;
use App\Enums\QuotaAction;
use App\Exceptions\QuotaExceededException;
use App\Models\Post;
use App\Services\Llm\LlmClient;
use App\Services\Llm\LlmException;
use App\Services\Planning\PlanSchema;
use App\Services\Planning\PromptBuilder;
use App\Services\Quota\QuotaGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class RegeneratePost implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public Post $post,
        public ?string $comment = null,
    ) {}

    public function handle(LlmClient $llm, PromptBuilder $prompts): void
    {
        $post = $this->post->fresh();

        if (! $post) {
            return;
        }

        // El job no depende de sesión: fija la marca del post.
        app()->instance('brand', $post->brand);

        // La cuota computa contra el mes del contenido del post.
        $year = $post->monthlyPlan->year;
        $month = $post->monthlyPlan->month;
        app()->instance('usage_period', [$year, $month]);

        if ($post->status !== PostStatus::Drafted) {
            return;
        }

        // Re-chequeo de cuota: si otro job/petición agotó el cupo entre el
        // dispatch y ahora, se aborta sin tocar el post.
        $quota = app(QuotaGuard::class);
        try {
            $quota->check($post->brand, QuotaAction::Regenerations, $year, $month);
        } catch (QuotaExceededException) {
            return;
        }

        try {
            ['system' => $system, 'user' => $user] = $prompts->forPost($post, $this->comment);

            $result = $llm->complete($system, $user, PlanSchema::single(), 'post_regeneration');

            foreach (['hashtags', 'design_payload'] as $field) {
                if (isset($result[$field]) && is_string($result[$field])) {
                    $decoded = json_decode($result[$field], true);
                    $result[$field] = is_array($decoded) ? $decoded : null;
                }
            }

            // Actualización del post + consumo de cuota en la misma
            // transacción: un fallo del LLM o de la escritura no incrementa.
            DB::transaction(function () use ($post, $result, $quota, $year, $month) {
                $post->update([
                    'hook' => $result['hook'] ?? $post->hook,
                    'copy' => $result['copy'] ?? $post->copy,
                    'hashtags' => $result['hashtags'] ?? $post->hashtags,
                    'design_payload' => $result['design_payload'] ?? $post->design_payload,
                    'background_prompt' => $result['background_prompt'] ?? $post->background_prompt,
                    'photo_keywords' => $result['photo_keywords'] ?? $post->photo_keywords,
                    'icon' => PostIcon::normalize($result['icon'] ?? $post->icon?->value),
                    'regeneration_error' => null,
                ]);

                $quota->record($post->brand, QuotaAction::Regenerations, $year, $month);
            });
        } catch (LlmException $e) {
            $post->update(['regeneration_error' => $e->getMessage()]);

            throw $e;
        }
    }
}
