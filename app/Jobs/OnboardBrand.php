<?php

namespace App\Jobs;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Models\Brand;
use App\Services\Llm\LlmClient;
use App\Services\Llm\LlmException;
use App\Services\Onboarding\BrandScraper;
use App\Services\Onboarding\OnboardingSchema;
use App\Services\Onboarding\SocialProfile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Onboarding de marca con IA (spec §8.1): recolecta la web con
 * BrandScraper y pide una propuesta al LLM. La propuesta queda en
 * brands.onboarding_proposal como borrador — nunca escribe en
 * brand_profile, pilares ni estrategias (el operador la aplica).
 */
class OnboardBrand implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * @param  array{social_handle?: string, posts?: array<int, string>}  $samples
     */
    public function __construct(
        public Brand $brand,
        public array $samples = [],
    ) {}

    public function handle(LlmClient $llm, BrandScraper $scraper, SocialProfile $social): void
    {
        // El job no depende de sesión: fija la marca para el scope y los logs.
        app()->instance('brand', $this->brand);

        try {
            // Marca sin web cuya presencia principal es una red social:
            // se lee el perfil público en vez de rastrear la "web".
            $detected = SocialProfile::detect($this->brand->website_url, $this->samples['social_handle'] ?? null);

            $collected = match (true) {
                $detected !== null => $social->read($detected),
                filled($this->brand->website_url) => $scraper->collect($this->brand->website_url),
                default => ['ok' => false, 'error' => 'La marca no tiene sitio web.', 'colors' => [], 'pages' => []],
            };
            $isSocial = ($collected['source'] ?? null) === 'social';

            $posts = collect($this->samples['posts'] ?? [])
                ->map(fn ($p) => trim((string) $p))
                ->filter()
                ->take(5)
                ->values()
                ->all();

            // En modo social el usuario ya es la fuente leída: sin perfil
            // legible solo los posts de ejemplo justifican la llamada.
            $hasSamples = $posts !== []
                || (! $isSocial && filled($this->samples['social_handle'] ?? null));

            if (! ($collected['ok'] ?? false) && ! $hasSamples) {
                $this->brand->forceFill([
                    'onboarding_proposal' => [
                        'error' => $isSocial
                            ? $collected['error']
                            : 'No se pudo leer el sitio web: '
                                .($collected['error'] ?? 'error desconocido')
                                .' Rellena la marca a mano o pega posts de ejemplo.',
                        'generated_at' => now()->toIso8601String(),
                    ],
                ])->save();

                return;
            }

            $networks = collect($this->brand->plan?->networks ?? [])
                ->map(fn ($n) => Network::tryFrom((string) $n))
                ->filter()
                ->values()
                ->all();

            try {
                $result = $llm->complete(
                    $this->systemPrompt(),
                    $this->userPrompt($collected, $posts, $networks),
                    OnboardingSchema::proposal($networks),
                    'brand_onboarding',
                );
            } catch (LlmException $e) {
                $this->brand->forceFill([
                    'onboarding_proposal' => [
                        'error' => 'La IA no pudo generar la propuesta: '.$e->getMessage(),
                        'generated_at' => now()->toIso8601String(),
                    ],
                ])->save();

                return;
            }

            $proposal = $this->normalize($result, $networks, $collected['colors'] ?? []);

            // La foto de perfil es un candidato a logo: solo se aplica si
            // el operador pulsa "Usar foto de perfil como logo".
            if (filled($collected['profile_image_url'] ?? null)) {
                $proposal['logo_candidate_url'] = $collected['profile_image_url'];
            }

            $this->brand->forceFill(['onboarding_proposal' => $proposal])->save();
        } finally {
            Cache::forget("onboarding:{$this->brand->id}");
        }
    }

    private function systemPrompt(): string
    {
        return 'Eres un estratega de marketing senior. A partir del contenido del sitio web de una marca '
            .'y de ejemplos de sus publicaciones, propones su configuración inicial para una plataforma '
            .'de planificación de contenido en redes sociales. Responde en español. Sé concreto: nada de '
            .'generalidades que valgan para cualquier marca.';
    }

    /**
     * @param  array<string, mixed>  $collected
     * @param  array<int, string>  $posts
     * @param  array<int, Network>  $networks
     */
    private function userPrompt(array $collected, array $posts, array $networks): string
    {
        if (($collected['source'] ?? null) === 'social') {
            return $this->socialUserPrompt($collected, $posts, $networks);
        }

        $parts = [
            "Marca: {$this->brand->name}",
            'Sitio web: '.($this->brand->website_url ?? 'n/d'),
        ];

        if ($collected['title'] ?? null) {
            $parts[] = 'Título de la home: '.$collected['title'];
        }
        if ($collected['meta_description'] ?? null) {
            $parts[] = 'Meta description: '.$collected['meta_description'];
        }
        if ($collected['home_text'] ?? null) {
            $parts[] = "Texto de la home:\n".$collected['home_text'];
        }
        foreach ($collected['pages'] ?? [] as $page) {
            $parts[] = "Página {$page['url']}:\n".$page['text'];
        }
        if (($collected['colors'] ?? []) !== []) {
            $parts[] = 'Colores detectados en la web: '.implode(', ', $collected['colors']);
        }
        if ($handle = ($this->samples['social_handle'] ?? null)) {
            $parts[] = "Usuario en redes: {$handle}";
        }
        foreach ($posts as $i => $post) {
            $parts[] = 'Post de ejemplo '.($i + 1).":\n".$post;
        }

        return implode("\n\n", [...$parts, ...$this->closingInstructions($networks)]);
    }

    /**
     * Prompt para marcas sin web: perfil público de la red + posts de
     * ejemplo, sin inventar lo que no aparece.
     *
     * @param  array<string, mixed>  $collected
     * @param  array<int, string>  $posts
     * @param  array<int, Network>  $networks
     */
    private function socialUserPrompt(array $collected, array $posts, array $networks): string
    {
        $label = SocialProfile::LABELS[$collected['network']] ?? $collected['network'];

        $parts = [
            "Marca: {$this->brand->name}",
            "La marca no tiene sitio web; su presencia principal es {$label}: {$collected['profile_url']}",
        ];

        if ($collected['title'] ?? null) {
            $parts[] = 'Nombre del perfil: '.$collected['title'];
        }
        if ($collected['meta_description'] ?? null) {
            $parts[] = 'Bio/descripción: '.$collected['meta_description'];
        }
        if ($collected['stats'] ?? null) {
            $parts[] = 'Estadísticas públicas: '.$collected['stats'];
        }
        if (($collected['colors'] ?? []) !== []) {
            $parts[] = 'Colores de la foto de perfil: '.implode(', ', $collected['colors']);
        }
        if (! ($collected['ok'] ?? false)) {
            $parts[] = "No se pudo leer el perfil de {$label}; apóyate en el nombre y los posts de ejemplo.";
        }
        foreach ($posts as $i => $post) {
            $parts[] = 'Post de ejemplo '.($i + 1).":\n".$post;
        }

        $parts[] = 'Infiere sector, tono y audiencia de la bio y los posts de ejemplo; no inventes datos que no estén ahí.';

        return implode("\n\n", [...$parts, ...$this->closingInstructions($networks)]);
    }

    /**
     * @param  array<int, Network>  $networks
     * @return array<int, string>
     */
    private function closingInstructions(array $networks): array
    {
        return [
            'Redes del plan contratado (las únicas permitidas): '
                .implode(', ', array_map(fn (Network $n) => $n->value, $networks)),
            'Genera la propuesta con emit_result: description (≤300), tone_of_voice, audience, cta, '
                .'base_hashtags (≤8, con #), 4-6 pillars cuyos weight sumen 100, una strategy por cada red '
                .'del plan (posts_per_week 1-7, formats solo de los permitidos por la red, focus) y palette '
                .'{dark, light, accent, secondary} en #RRGGBB eligiendo entre los colores detectados o '
                .'proponiendo con contraste razonable.',
        ];
    }

    /**
     * Normaliza la respuesta del LLM a la forma persistida en
     * onboarding_proposal (incluye generated_at).
     *
     * @param  array<string, mixed>  $result
     * @param  array<int, Network>  $networks
     * @param  array<int, string>  $detectedColors
     * @return array<string, mixed>
     */
    private function normalize(array $result, array $networks, array $detectedColors): array
    {
        return [
            'description' => Str::limit(trim((string) ($result['description'] ?? '')), 300, ''),
            'tone_of_voice' => trim((string) ($result['tone_of_voice'] ?? '')),
            'audience' => trim((string) ($result['audience'] ?? '')),
            'cta' => trim((string) ($result['cta'] ?? '')),
            'base_hashtags' => collect($result['base_hashtags'] ?? [])
                ->map(fn ($t) => '#'.ltrim(trim((string) $t), '#'))
                ->filter(fn ($t) => $t !== '#')
                ->unique()
                ->take(8)
                ->values()
                ->all(),
            'pillars' => $this->normalizePillars($result['pillars'] ?? []),
            'strategies' => $this->normalizeStrategies($result['strategies'] ?? [], $networks),
            'palette' => $this->normalizePalette($result['palette'] ?? [], $detectedColors),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * 4–6 pilares con pesos enteros ≥1 renormalizados a sumar 100
     * (largest-remainder sobre los pesos del LLM).
     *
     * @param  array<int, mixed>  $pillars
     * @return array<int, array{name: string, description: string, weight: int}>
     */
    private function normalizePillars(array $pillars): array
    {
        $pillars = collect($pillars)
            ->filter(fn ($p) => is_array($p) && filled($p['name'] ?? null))
            ->take(6)
            ->values()
            ->all();

        $n = count($pillars);
        if ($n === 0) {
            return [];
        }

        $raw = array_map(fn ($p) => max(0.0, (float) ($p['weight'] ?? 0)), $pillars);
        $rawTotal = array_sum($raw);
        if ($rawTotal <= 0) {
            $raw = array_fill(0, $n, 1.0);
            $rawTotal = (float) $n;
        }

        $exact = array_map(fn ($w) => $w * 100 / $rawTotal, $raw);
        $weights = array_map(fn ($e) => max(1, (int) floor($e)), $exact);
        $left = 100 - array_sum($weights);

        if ($left > 0) {
            $fracs = [];
            foreach ($exact as $i => $e) {
                $fracs[$i] = $e - floor($e);
            }
            arsort($fracs);
            $keys = array_keys($fracs);
            for ($i = 0; $left > 0; $i++, $left--) {
                $weights[$keys[$i % $n]]++;
            }
        }
        while ($left < 0) {
            // El mínimo de 1 puede haber pasado de 100: recorta del mayor.
            $max = array_search(max($weights), $weights, true);
            if ($weights[$max] <= 1) {
                break;
            }
            $weights[$max]--;
            $left++;
        }

        return array_map(fn ($p, $i) => [
            'name' => Str::limit(trim((string) $p['name']), 100, ''),
            'description' => trim((string) ($p['description'] ?? '')),
            'weight' => $weights[$i],
        ], $pillars, array_keys($pillars));
    }

    /**
     * Una estrategia por cada red del plan (las demás se ignoran):
     * posts_per_week ∈ [1,7] y formats ⊆ PostFormat::allowedFor.
     *
     * @param  array<int, mixed>  $strategies
     * @param  array<int, Network>  $networks
     * @return array<int, array{network: string, posts_per_week: int, formats: array<int, string>, focus: string}>
     */
    private function normalizeStrategies(array $strategies, array $networks): array
    {
        $byNetwork = collect($strategies)
            ->filter(fn ($s) => is_array($s))
            ->keyBy(fn ($s) => Str::lower((string) ($s['network'] ?? '')));

        $normalized = [];
        foreach ($networks as $network) {
            $allowed = array_map(fn (PostFormat $f) => $f->value, PostFormat::allowedFor($network));
            $entry = $byNetwork->get($network->value, []);

            $formats = collect($entry['formats'] ?? [])
                ->map(fn ($f) => Str::lower(trim((string) $f)))
                ->filter(fn ($f) => in_array($f, $allowed, true))
                ->unique()
                ->values()
                ->all();

            $normalized[] = [
                'network' => $network->value,
                'posts_per_week' => max(1, min(7, (int) ($entry['posts_per_week'] ?? 2))),
                'formats' => $formats === [] ? $allowed : $formats,
                'focus' => trim((string) ($entry['focus'] ?? '')),
            ];
        }

        return $normalized;
    }

    /**
     * Paleta {dark, light, accent, secondary} sanitizada a #RRGGBB;
     * fallback por clave: colores detectados y luego defaults.
     *
     * @param  array<string, mixed>  $palette
     * @param  array<int, string>  $detectedColors
     * @return array{dark: string, light: string, accent: string, secondary: string}
     */
    private function normalizePalette(array $palette, array $detectedColors): array
    {
        $defaults = [
            'dark' => '#1A2A42',
            'light' => '#F8F5F0',
            'accent' => '#27C2A5',
            'secondary' => '#7854D2',
        ];

        $detected = collect($detectedColors)
            ->map(fn ($c) => $this->sanitizeHex((string) $c))
            ->filter()
            ->values();

        $normalized = [];
        $i = 0;
        foreach ($defaults as $key => $fallback) {
            $normalized[$key] = $this->sanitizeHex((string) ($palette[$key] ?? ''))
                ?? $detected->get($i)
                ?? $fallback;
            $i++;
        }

        return $normalized;
    }

    private function sanitizeHex(string $color): ?string
    {
        $color = trim($color);
        if (preg_match('/^#?([0-9a-fA-F]{6})$/', $color, $m)) {
            return '#'.Str::upper($m[1]);
        }
        if (preg_match('/^#?([0-9a-fA-F]{3})$/', $color, $m)) {
            $hex = collect(str_split($m[1]))->map(fn ($c) => $c.$c)->implode('');

            return '#'.Str::upper($hex);
        }

        return null;
    }
}
