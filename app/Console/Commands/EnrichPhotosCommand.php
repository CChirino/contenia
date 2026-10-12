<?php

namespace App\Console\Commands;

use App\Enums\PostIcon;
use App\Models\Post;
use App\Services\Llm\LlmClient;
use Illuminate\Console\Command;

class EnrichPhotosCommand extends Command
{
    protected $signature = 'posts:enrich-photos {year} {month} {--force : Re-procesa todos los posts del mes aunque ya tengan keywords}';

    protected $description = 'Rellena photo_keywords, icon y background_prompt de los posts del mes que no los tengan (lotes de 15).';

    public function handle(LlmClient $llm): int
    {
        $year = (int) $this->argument('year');
        $month = (int) $this->argument('month');

        $posts = Post::query()
            ->when(! $this->option('force'), fn ($q) => $q->whereNull('photo_keywords'))
            ->whereHas('monthlyPlan', fn ($q) => $q->where('year', $year)->where('month', $month))
            ->with('contentPillar')
            ->orderBy('scheduled_at')
            ->get();

        if ($posts->isEmpty()) {
            $this->info('Todos los posts ya tienen photo_keywords.');

            return self::SUCCESS;
        }

        $this->info("{$posts->count()} posts sin photo_keywords; enriqueciendo en lotes de 15…");

        $schema = [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'photo_keywords' => ['type' => 'string'],
                            'icon' => ['type' => 'string', 'enum' => PostIcon::values()],
                            'background_prompt' => ['type' => 'string'],
                        ],
                        'required' => ['id', 'photo_keywords', 'icon', 'background_prompt'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['items'],
            'additionalProperties' => false,
        ];

        $system = <<<PROMPT
        Eres el community manager de Saberé, una plataforma de gestión escolar para colegios privados en Venezuela. Para cada post debes proponer los campos visuales del diseño:
        - photo_keywords: 2-3 palabras EN INGLÉS para buscar una FOTO DE STOCK de ambiente (no el concepto literal): escena escolar u oficina, p. ej. 'school hallway lockers', 'teacher laptop desk', 'notebook pencils desk'. Sin niños ni menores; adultos solo si son docentes/directivos, preferible manos, objetos y espacios. Usa sustantivos concretos y fotografiables típicos de bancos de fotos (desk, notebook, laptop, classroom, whiteboard, backpack, books, tablet, pencils, hallway); evita abstractos (rubric, assessment, performance, strategy, biometric).
        - icon: elige del catálogo [{$this->catalog()}] el que mejor simbolice el tema.
        - background_prompt: EN INGLÉS, una ESCENA FOTOGRÁFICA de respaldo (sin personas, sin letreros, sin pantallas con texto), máximo 25 palabras. No menciones estilos de ilustración.
        Responde únicamente con la herramienta emit_result, devolviendo un item por cada post listado.
        PROMPT;

        $updated = 0;

        foreach ($posts->chunk(15) as $i => $batch) {
            $user = $batch->map(fn (Post $p) => sprintf(
                '- id=%d | red=%s | pilar=%s | hook=%s | copy=%s',
                $p->id,
                $p->network->value,
                $p->contentPillar?->name ?? 'n/a',
                $p->hook,
                mb_substr((string) $p->copy, 0, 200),
            ))->implode("\n");

            try {
                $result = $llm->complete($system, $user, $schema, 'enrich_photos');
                $items = collect($result['items'] ?? [])->keyBy('id');

                foreach ($batch as $post) {
                    $item = $items->get($post->id);
                    if (! $item) {
                        continue;
                    }
                    $post->update([
                        'photo_keywords' => filled($item['photo_keywords'] ?? null) ? trim($item['photo_keywords']) : null,
                        'icon' => PostIcon::normalize($item['icon'] ?? null),
                        'background_prompt' => filled($item['background_prompt'] ?? null) ? $item['background_prompt'] : $post->background_prompt,
                    ]);
                    $updated++;
                }

                $this->line("Lote {$i}: {$items->count()} items recibidos.");
            } catch (\Throwable $e) {
                $this->warn("Lote {$i} falló: {$e->getMessage()}");
            }
        }

        $this->info("Listo: {$updated} posts enriquecidos.");

        return self::SUCCESS;
    }

    protected function catalog(): string
    {
        return implode(', ', PostIcon::values());
    }
}
