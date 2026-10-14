<?php

namespace App\Jobs;

use App\Enums\PostIcon;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Services\Llm\LlmClient;
use App\Services\Llm\LlmException;
use App\Services\Planning\PlanSchema;
use App\Services\Planning\PromptBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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

        if ($post->status !== PostStatus::Drafted) {
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
        } catch (LlmException $e) {
            $post->update(['regeneration_error' => $e->getMessage()]);

            throw $e;
        }
    }
}
