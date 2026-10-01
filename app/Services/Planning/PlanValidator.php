<?php

namespace App\Services\Planning;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Models\CalendarEvent;
use App\Models\ContentPillar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PlanValidator
{
    /**
     * Valida los posts propuestos por el LLM para un mes.
     *
     * @param  array<int, array<string, mixed>>  $posts
     * @return array{valid: array<int, array<string, mixed>>, rejected: array<int, array{index: int, post: array<string, mixed>, reason: string}>}
     */
    public function validate(array $posts, int $year, int $month): array
    {
        $pillars = ContentPillar::all()
            ->keyBy(fn (ContentPillar $p) => mb_strtolower($p->name));

        $noPosting = CalendarEvent::where('no_posting', true)
            ->get()
            ->filter(fn (CalendarEvent $e) => $e->recurring || $e->date->year === $year)
            ->map(fn (CalendarEvent $e) => $e->date->format('m-d'))
            ->flip();

        $valid = [];
        $rejected = [];

        foreach ($posts as $index => $post) {
            $reasons = $this->validatePost(is_array($post) ? $post : [], $year, $month, $pillars, $noPosting);

            if ($reasons === []) {
                $post['content_pillar_id'] = $pillars->get(mb_strtolower($post['pillar']))->id;
                $valid[] = $post;
            } else {
                $reason = implode('; ', $reasons);
                $rejected[] = ['index' => $index, 'post' => $post, 'reason' => $reason];

                Log::warning("Post descartado de la grilla {$year}-{$month}: {$reason}", [
                    'post' => $post,
                ]);
            }
        }

        return ['valid' => $valid, 'rejected' => $rejected];
    }

    /**
     * @param  array<string, mixed>  $post
     * @param  Collection<string, ContentPillar>  $pillars
     * @param  Collection<string, mixed>  $noPosting
     * @return array<int, string>
     */
    protected function validatePost(array $post, int $year, int $month, $pillars, $noPosting): array
    {
        $reasons = [];

        foreach (['date', 'time', 'network', 'pillar', 'format', 'hook', 'copy'] as $field) {
            if (! isset($post[$field]) || ! is_string($post[$field]) || trim($post[$field]) === '') {
                $reasons[] = "campo requerido vacío o ausente: {$field}";
            }
        }

        if (empty($post['hashtags']) || ! is_array($post['hashtags'])) {
            $reasons[] = 'campo requerido vacío o ausente: hashtags';
        }

        if (empty($post['design_payload']) || ! is_array($post['design_payload'])) {
            $reasons[] = 'campo requerido vacío o ausente: design_payload';
        }

        if ($reasons !== []) {
            return $reasons;
        }

        $network = Network::tryFrom($post['network']);
        if (! $network) {
            $reasons[] = "red desconocida: {$post['network']}";
        } else {
            $format = PostFormat::tryFrom($post['format']);
            if (! $format) {
                $reasons[] = "formato desconocido: {$post['format']}";
            } elseif (! in_array($format, PostFormat::allowedFor($network), true)) {
                $reasons[] = "formato {$format->value} no permitido en {$network->value}";
            }
        }

        if (! $pillars->has(mb_strtolower($post['pillar']))) {
            $reasons[] = "pilar inexistente: {$post['pillar']}";
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', $post['date']);
        if (! $date || $date->format('Y-m-d') !== $post['date']) {
            $reasons[] = "fecha inválida: {$post['date']}";
        } else {
            if ($date->year !== $year || $date->month !== $month) {
                $reasons[] = "fecha fuera del mes: {$post['date']}";
            }

            if ($noPosting->has($date->format('m-d'))) {
                $reasons[] = "fecha marcada como sin publicación: {$post['date']}";
            }
        }

        return $reasons;
    }
}
