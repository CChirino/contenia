<?php

namespace App\Services\Planning;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Models\BrandProfile;
use App\Models\CalendarEvent;
use App\Models\ContentPillar;
use App\Models\MonthlyPlan;
use App\Models\NetworkStrategy;
use App\Models\Post;
use Carbon\CarbonImmutable;

class PromptBuilder
{
    /**
     * Prompts para generar la grilla de un mes completo.
     *
     * @return array{system: string, user: string}
     */
    public function forMonth(int $year, int $month): array
    {
        return [
            'system' => $this->systemPrompt(),
            'user' => $this->monthUserPrompt($year, $month),
        ];
    }

    /**
     * Prompts para regenerar un solo post manteniendo red/fecha/pilar.
     *
     * @return array{system: string, user: string}
     */
    public function forPost(Post $post, ?string $comment): array
    {
        return [
            'system' => $this->systemPrompt(),
            'user' => $this->postUserPrompt($post, $comment),
        ];
    }

    protected function systemPrompt(): string
    {
        $brand = BrandProfile::current();

        $hashtags = collect($brand->base_hashtags)->implode(' ');

        return <<<PROMPT
        Eres el community manager de Saberé, una plataforma de gestión escolar para colegios privados en Venezuela. Generas contenido para sus redes sociales.

        Perfil de marca:
        - Tono de voz: {$brand->tone_of_voice}
        - Audiencia: {$brand->audience}
        - Llamado a la acción frecuente: {$brand->cta}
        - URL: {$brand->url}
        - Hashtags base: {$hashtags}

        Reglas de estilo:
        - Escribe siempre en español de Venezuela (expresiones y referencias locales apropiadas).
        - Tono profesional pero cercano; no uses emojis en exceso (máximo uno por post).
        - Cada post es para UNA red concreta; adapta formato y tono a esa red.
        - Responde únicamente con la herramienta emit_result, respetando el esquema indicado.
        PROMPT;
    }

    protected function monthUserPrompt(int $year, int $month): string
    {
        $first = CarbonImmutable::create($year, $month, 1);
        $daysInMonth = $first->daysInMonth;

        $weekdays = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

        $events = CalendarEvent::all()
            ->filter(fn (CalendarEvent $e) => $e->date->month === $month
                && ($e->recurring || $e->date->year === $year))
            ->keyBy(fn (CalendarEvent $e) => $e->date->format('m-d'));

        $days = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = $first->setDay($day);
            $label = $date->format('Y-m-d').' ('.$weekdays[$date->dayOfWeek].')';

            if ($event = $events->get($date->format('m-d'))) {
                $label .= " — {$event->name}".($event->no_posting ? ' [SIN PUBLICACIÓN]' : '');
            }

            $days[] = $label;
        }

        $strategies = NetworkStrategy::all()
            ->map(function (NetworkStrategy $s) {
                $formats = implode(', ', $s->preferred_formats);
                $schedule = match ($s->network) {
                    Network::LinkedIn => '08:00 de lunes a viernes',
                    Network::Instagram => '18:00',
                    Network::TikTok => '19:00',
                };

                return "- {$s->network->value}: {$s->posts_per_week} posts por semana. "
                    ."Formatos preferidos: {$formats}. Formatos permitidos: "
                    .implode(', ', array_map(fn (PostFormat $f) => $f->value, PostFormat::allowedFor($s->network)))
                    .". Horario sugerido: {$schedule}. Enfoque: {$s->focus}";
            })
            ->implode("\n");

        $pillars = ContentPillar::all()
            ->map(fn (ContentPillar $p) => "- {$p->name} ({$p->weight}%): {$p->description}")
            ->implode("\n");

        $previous = MonthlyPlan::where('year', $first->subMonth()->year)
            ->where('month', $first->subMonth()->month)
            ->first();

        $previousSummary = 'No hubo plan el mes anterior.';
        if ($previous) {
            $lines = $previous->posts()
                ->with('contentPillar')
                ->orderBy('scheduled_at')
                ->get()
                ->map(fn (Post $p) => "- {$p->network->value} / ".($p->contentPillar->name ?? 'sin pilar').": {$p->hook}")
                ->implode("\n");

            $previousSummary = $lines === '' ? 'El plan anterior no tuvo posts.' : $lines;
        }

        return <<<PROMPT
        Genera la grilla de contenido para {$first->translatedFormat('F')} de {$year}.

        Días del mes:
        {$this->list($days)}

        Estrategia por red:
        {$strategies}

        Pilares de contenido (distribuye los posts según estos pesos):
        {$pillars}

        Resumen del mes anterior (no repitas temas):
        {$previousSummary}

        Reglas:
        - Respeta la cadencia semanal por red (±1 post por semana).
        - Distribuye los pilares según sus pesos.
        - No repitas el mismo tema entre redes en la misma semana.
        - No programes posts en días marcados [SIN PUBLICACIÓN].
        - design_payload.slides: 1 slide para image, 3-6 para carousel, 3-5 escenas para reel/video.
        - background_prompt describe una ilustración de fondo SIN texto y SIN personas reales.
        - "pillar" debe ser el nombre exacto de uno de los pilares listados.
        PROMPT;
    }

    protected function postUserPrompt(Post $post, ?string $comment): string
    {
        $post->loadMissing('contentPillar');

        $hashtags = collect($post->hashtags)->implode(' ');

        $prompt = <<<PROMPT
        Regenera este post manteniendo la misma red, fecha, pilar y formato. Devuelve una versión nueva de hook, copy, hashtags, design_payload y background_prompt.

        Post actual:
        - Red: {$post->network->value}
        - Fecha y hora: {$post->scheduled_at->format('Y-m-d H:i')}
        - Pilar: {$post->contentPillar->name}
        - Formato: {$post->format->value}
        - Hook: {$post->hook}
        - Copy: {$post->copy}
        - Hashtags: {$hashtags}
        PROMPT;

        if ($comment) {
            $prompt .= "\n\nComentario del usuario (tenlo en cuenta): {$comment}";
        }

        return $prompt;
    }

    /**
     * @param  array<int, string>  $items
     */
    protected function list(array $items): string
    {
        return collect($items)->map(fn (string $i) => "- {$i}")->implode("\n");
    }
}
