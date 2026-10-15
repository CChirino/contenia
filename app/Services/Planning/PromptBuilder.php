<?php

namespace App\Services\Planning;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Enums\PostIcon;
use App\Models\Brand;
use App\Models\BrandProfile;
use App\Models\CalendarEvent;
use App\Models\ContentPillar;
use App\Models\NetworkStrategy;
use App\Models\Post;
use App\Services\Quota\QuotaGuard;
use Carbon\CarbonImmutable;

class PromptBuilder
{
    /**
     * Prompts para generar la grilla de una semana del mes
     * (rango $from..$to ya recortado al mes).
     *
     * @param  array<int, array{hook: string, pillar: string, network: string}>  $alreadyPlanned
     * @param  array<string, int>|null  $maxPerNetwork  Tope de posts por red (cuota); null = sin tope.
     * @return array{system: string, user: string}
     */
    public function forWeek(int $year, int $month, CarbonImmutable $from, CarbonImmutable $to, array $alreadyPlanned, ?array $maxPerNetwork = null): array
    {
        return [
            'system' => $this->systemPrompt(),
            'user' => $this->weekUserPrompt($year, $month, $from, $to, $alreadyPlanned, $maxPerNetwork),
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

    /**
     * Presentación del rol en los prompts: nombre de la marca activa y, si el
     * perfil la tiene, su descripción (qué hace, para quién).
     */
    public function brandIntro(): string
    {
        $intro = 'Eres el community manager de '.Brand::current()->name;
        $description = trim((string) (BrandProfile::current()->description ?? ''));

        if ($description !== '') {
            $description = rtrim($description, ". \t\n");
            $intro .= ', '.(ctype_upper($description[0]) ? lcfirst($description) : $description);
        }

        return $intro.'.';
    }

    /**
     * Regla de photo_keywords compartida por generación y posts:enrich-photos.
     */
    public function photoKeywordsRule(): string
    {
        return "photo_keywords: 2-3 palabras EN INGLÉS para buscar una FOTO DE STOCK de ambiente (no el concepto literal): escena del sector de la marca (según su descripción) u oficina, p. ej. 'office laptop desk', 'notebook pencils desk', 'team meeting table'. Sin niños ni menores; adultos solo si son claros profesionales, preferible manos, objetos y espacios. Usa sustantivos concretos y fotografiables típicos de bancos de fotos (desk, notebook, laptop, whiteboard, books, tablet, hallway, storefront, workshop, kitchen); evita abstractos (rubric, assessment, performance, strategy, biometric).";
    }

    protected function systemPrompt(): string
    {
        $brand = BrandProfile::current();

        $hashtags = collect($brand->base_hashtags)->implode(' ');

        return <<<PROMPT
        {$this->brandIntro()} Generas contenido para sus redes sociales.

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

    /**
     * @param  array<int, array{hook: string, pillar: string, network: string}>  $alreadyPlanned
     * @param  array<string, int>|null  $maxPerNetwork
     */
    protected function weekUserPrompt(int $year, int $month, CarbonImmutable $from, CarbonImmutable $to, array $alreadyPlanned, ?array $maxPerNetwork = null): string
    {
        $weekdays = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

        $events = CalendarEvent::all()
            ->filter(fn (CalendarEvent $e) => $e->date->month === $month
                && ($e->recurring || $e->date->year === $year))
            ->keyBy(fn (CalendarEvent $e) => $e->date->format('m-d'));

        $days = [];
        $daysInRange = 0;
        for ($date = $from; $date <= $to; $date = $date->addDay()) {
            $daysInRange++;
            $label = $date->format('Y-m-d').' ('.$weekdays[$date->dayOfWeek].')';

            if ($event = $events->get($date->format('m-d'))) {
                $label .= " — {$event->name}".($event->no_posting ? ' [SIN PUBLICACIÓN]' : '');
            }

            $days[] = $label;
        }

        $quota = app(QuotaGuard::class);

        $strategies = NetworkStrategy::all()
            ->filter(fn (NetworkStrategy $s) => $quota->allowsNetwork(app('brand'), $s->network))
            ->map(function (NetworkStrategy $s) use ($daysInRange, $maxPerNetwork) {
                $formats = implode(', ', $s->preferred_formats);
                $schedule = match ($s->network) {
                    Network::LinkedIn => '08:00 de lunes a viernes',
                    Network::Instagram => '18:00',
                    Network::TikTok => '19:00',
                };

                $thisWeek = (int) round($s->posts_per_week * $daysInRange / 7);
                if ($s->posts_per_week > 0 && $daysInRange >= 3) {
                    $thisWeek = max(1, $thisWeek);
                }
                if ($maxPerNetwork !== null) {
                    $thisWeek = min($thisWeek, $maxPerNetwork[$s->network->value] ?? 0);
                }

                return "- {$s->network->value}: {$thisWeek} posts para esta semana (cadencia habitual {$s->posts_per_week}/semana). "
                    ."Formatos preferidos: {$formats}. Formatos permitidos: "
                    .implode(', ', array_map(fn (PostFormat $f) => $f->value, PostFormat::allowedFor($s->network)))
                    .". Horario sugerido: {$schedule}. Enfoque: {$s->focus}";
            })
            ->implode("\n");

        $pillars = ContentPillar::all()
            ->map(fn (ContentPillar $p) => "- {$p->name} ({$p->weight}%): {$p->description}")
            ->implode("\n");

        $plannedSummary = 'Aún no hay posts planificados.';
        if ($alreadyPlanned !== []) {
            $plannedSummary = collect($alreadyPlanned)
                ->map(fn (array $p) => "- {$p['network']} / {$p['pillar']}: {$p['hook']}")
                ->implode("\n");
        }

        return <<<PROMPT
        Genera el contenido de la semana del {$from->format('Y-m-d')} al {$to->format('Y-m-d')} de {$from->translatedFormat('F')} de {$year}.

        Días de esta semana:
        {$this->list($days)}

        Estrategia por red:
        {$strategies}

        Pilares de contenido (distribuye los posts según estos pesos):
        {$pillars}

        Posts ya planificados este mes y el anterior (no repitas temas):
        {$plannedSummary}

        Reglas:
        - Respeta la cantidad de posts indicada para esta semana por red (±1).
        - Distribuye los pilares según sus pesos.
        - No repitas el mismo tema entre redes en la misma semana.
        - No programes posts en días marcados [SIN PUBLICACIÓN] ni fuera del rango indicado.
        - design_payload.slides: 1 slide para image, 3-6 para carousel, 3-5 escenas para reel/video.
        - {$this->photoKeywordsRule()}
        - icon: elige del catálogo [{$this->iconCatalog()}] el que mejor simbolice el tema.
        - background_prompt debe estar EN INGLÉS y describir una ESCENA FOTOGRÁFICA de respaldo (sin personas, sin letreros, sin pantallas con texto), máximo 25 palabras. No menciones estilos de ilustración.
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

    protected function iconCatalog(): string
    {
        return implode(', ', PostIcon::values());
    }

    /**
     * @param  array<int, string>  $items
     */
    protected function list(array $items): string
    {
        return collect($items)->map(fn (string $i) => "- {$i}")->implode("\n");
    }
}
