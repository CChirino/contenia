<?php

namespace App\Jobs;

use App\Enums\PostIcon;
use App\Enums\QuotaAction;
use App\Models\Post;
use App\Services\Llm\LlmClient;
use App\Services\Media\MediaInspector;
use App\Services\Planning\PlanSchema;
use App\Services\Planning\PromptBuilder;
use App\Services\Quota\QuotaGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Redacta un post "desde archivo" en dos llamadas: visión (describe el
 * material, se omite si $reuseDescription y ya existe) y redacción con
 * el modelo de siempre. Un fallo deja compose_error y el post editable;
 * el botón Reintentar (posts.compose) lo reencola.
 */
class ComposeFromMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public Post $post,
        public bool $reuseDescription = false,
        public ?string $comment = null,
    ) {}

    public function handle(LlmClient $llm, PromptBuilder $prompts, MediaInspector $inspector): void
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

        $quota = app(QuotaGuard::class);

        // Una regeneración (reutiliza la descripción de visión) consume
        // el cupo de regeneraciones, como RegeneratePost.
        if ($this->reuseDescription) {
            try {
                $quota->check($post->brand, QuotaAction::Regenerations, $year, $month);
            } catch (Throwable $e) {
                $post->update([
                    'composing_at' => null,
                    'compose_error' => $e->getMessage(),
                ]);

                return;
            }
        }

        try {
            // Paso 1 — visión: solo si falta la descripción o se pidió
            // volver a mirar el material.
            $description = $post->media_description;

            if (! ($this->reuseDescription && $description)) {
                $absPath = Storage::disk('local')->path((string) $post->source_path);

                ['system' => $vSystem, 'user' => $vUser] = $prompts->forMediaDescription($post);

                $description = $llm->describeImages(
                    $vSystem,
                    $vUser,
                    $inspector->framesAsDataUrls($absPath, (string) $post->source_mime),
                    PlanSchema::mediaDescription(),
                    'media_description'
                );
            }

            // Paso 2 — redacción con el modelo de siempre.
            ['system' => $system, 'user' => $user] = $prompts->forMedia($post, $this->comment);

            $pillarIds = $post->brand->pillars()->pluck('id')->all();

            $result = $llm->complete(
                $system, $user, PlanSchema::fromMedia($pillarIds), 'post_from_media'
            );

            foreach (['hashtags', 'design_payload', 'media_description'] as $field) {
                if (isset($result[$field]) && is_string($result[$field])) {
                    $decoded = json_decode($result[$field], true);
                    $result[$field] = is_array($decoded) ? $decoded : null;
                }
            }

            $pillarId = (int) ($result['content_pillar_id'] ?? 0);

            DB::transaction(function () use ($post, $result, $description, $pillarId, $pillarIds, $quota, $year, $month) {
                $post->update([
                    'hook' => $result['hook'] ?? $post->hook,
                    'copy' => $result['copy'] ?? $post->copy,
                    'hashtags' => $result['hashtags'] ?? $post->hashtags,
                    'design_payload' => $result['design_payload'] ?? $post->design_payload,
                    'content_pillar_id' => in_array($pillarId, $pillarIds, true)
                        ? $pillarId
                        : $post->content_pillar_id,
                    'icon' => PostIcon::normalize($result['icon'] ?? null) ?? $post->icon,
                    'media_description' => $description,
                    'composing_at' => null,
                    'compose_error' => null,
                ]);

                $quota->record(
                    $post->brand,
                    $this->reuseDescription ? QuotaAction::Regenerations : QuotaAction::PostsGenerated,
                    $year,
                    $month
                );
            });
        } catch (Throwable $e) {
            $post->update([
                'composing_at' => null,
                'compose_error' => $e->getMessage(),
            ]);
        }
    }
}
