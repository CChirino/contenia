<?php

namespace App\Console\Commands;

use App\Enums\BrandStatus;
use App\Enums\PostIcon;
use App\Models\Brand;
use App\Models\Post;
use App\Services\Llm\LlmClient;
use App\Services\Planning\PromptBuilder;
use Illuminate\Console\Command;

class EnrichPhotosCommand extends Command
{
    protected $signature = 'posts:enrich-photos {year} {month} {--brand= : Slug de la marca (por defecto, la primera activa)} {--force : Re-procesa todos los posts del mes aunque ya tengan keywords}';

    protected $description = 'Rellena photo_keywords, icon y background_prompt de los posts del mes que no los tengan (lotes de 15).';

    public function handle(LlmClient $llm, PromptBuilder $prompts): int
    {
        $year = (int) $this->argument('year');
        $month = (int) $this->argument('month');

        if ($slug = $this->option('brand')) {
            $brand = Brand::where('slug', $slug)->first();
        } else {
            $brand = Brand::where('status', BrandStatus::Active)->orderBy('id')->first();
            if ($brand) {
                $this->warn("Sin --brand: usando la primera marca activa ({$brand->slug}).");
            }
        }

        if (! $brand) {
            $this->error('Marca no encontrada: '.($slug ?? '(ninguna activa)'));

            return self::FAILURE;
        }

        app()->instance('brand', $brand);

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
        {$prompts->brandIntro()} Para cada post debes proponer los campos visuales del diseño:
        - {$prompts->photoKeywordsRule()}
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
