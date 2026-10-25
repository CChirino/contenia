<?php

namespace App\Services\Onboarding;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Models\Brand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Aplica por secciones la propuesta de onboarding (borrador en
 * brands.onboarding_proposal) a las tablas reales: brand_profile,
 * content_pillars, network_strategies y brands.palette. Cada sección
 * marca onboarding_proposal.applied.{sección}; con las cuatro
 * aplicadas se fija brands.onboarded_at.
 *
 * Lo usan OnboardingApplyController (sección a sección, con datos del
 * formulario) y OnboardingController@applyAll/manualSetup (wizard).
 *
 * Las rutas que lo llaman no siempre pasan por SetActiveBrand: cada
 * método fija la marca como activa para que BelongsToBrand asigne
 * brand_id y los scopes filtren correctamente.
 */
class ProposalApplier
{
    private const SECTIONS = ['profile', 'pillars', 'strategies', 'palette'];

    /**
     * Aplica las cuatro secciones en una sola transacción con los datos
     * de la propuesta (replace=true para pilares).
     */
    public function applyAll(Brand $brand): void
    {
        $proposal = $brand->onboarding_proposal ?? [];

        abort_if(($proposal['error'] ?? null) !== null, 422, 'La propuesta tiene un error.');

        DB::transaction(function () use ($brand, $proposal) {
            $this->applyProfile($brand, $proposal);
            $this->applyPillars($brand, [
                'pillars' => $proposal['pillars'] ?? [],
                'replace' => true,
            ]);
            $this->applyStrategies($brand, ['strategies' => $proposal['strategies'] ?? []]);
            $this->applyPalette($brand, ['palette' => $proposal['palette'] ?? []]);
        });
    }

    /**
     * Perfil: escribe description/tone/audience/cta/hashtags en
     * brand_profile (updateOrCreate del perfil de esa marca).
     *
     * @param  array<string, mixed>  $data
     */
    public function applyProfile(Brand $brand, array $data): void
    {
        app()->instance('brand', $brand);

        $hashtags = collect($data['base_hashtags'] ?? [])
            ->map(fn ($t) => '#'.ltrim(trim((string) $t), '#'))
            ->filter(fn ($t) => $t !== '#')
            ->unique()
            ->take(8)
            ->values()
            ->all();

        // firstOrCreate con los defaults de BrandProfile::current():
        // url y palette son NOT NULL sin default en la tabla.
        $profile = $brand->profile()->firstOrCreate([], [
            'tone_of_voice' => '',
            'audience' => '',
            'cta' => '',
            'url' => $brand->website_url ?? '',
            'base_hashtags' => [],
            'palette' => [],
        ]);

        $profile->update([
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'tone_of_voice' => trim((string) ($data['tone_of_voice'] ?? '')),
            'audience' => trim((string) ($data['audience'] ?? '')),
            'cta' => trim((string) ($data['cta'] ?? '')),
            'base_hashtags' => $hashtags,
        ]);

        $this->markApplied($brand, 'profile');
    }

    /**
     * Pilares: los pesos deben sumar 100. Si la marca ya tiene pilares
     * hace falta replace=true; si no, se crean directamente.
     *
     * @param  array{pillars: array<int, array{name: string, description?: ?string, weight: int}>, replace?: bool}  $data
     */
    public function applyPillars(Brand $brand, array $data): void
    {
        app()->instance('brand', $brand);

        $pillars = collect($data['pillars'] ?? []);

        $sum = $pillars->sum(fn ($p) => (int) ($p['weight'] ?? 0));
        if ($pillars->isEmpty() || $sum !== 100) {
            throw ValidationException::withMessages([
                'pillars' => "Los pesos deben sumar 100 (suman {$sum}).",
            ]);
        }

        $existing = $brand->pillars()->count();
        if ($existing > 0 && ! ($data['replace'] ?? false)) {
            throw ValidationException::withMessages([
                'pillars' => "La marca ya tiene {$existing} pilares: confirma el reemplazo.",
            ]);
        }

        $brand->pillars()->delete();

        foreach ($pillars as $pillar) {
            $brand->pillars()->create([
                'name' => trim((string) $pillar['name']),
                'description' => trim((string) ($pillar['description'] ?? '')),
                'weight' => (int) $pillar['weight'],
            ]);
        }

        $this->markApplied($brand, 'pillars');
    }

    /**
     * Estrategias por red: updateOrCreate por red, solo para redes del
     * plan de la marca (las demás se ignoran). Devuelve cuántas aplicó.
     *
     * @param  array{strategies: array<int, array<string, mixed>>}  $data
     */
    public function applyStrategies(Brand $brand, array $data): int
    {
        app()->instance('brand', $brand);

        $planNetworks = collect($brand->plan?->networks ?? [])
            ->map(fn ($n) => Network::tryFrom((string) $n))
            ->filter()
            ->values();

        $applied = 0;

        foreach ($data['strategies'] ?? [] as $entry) {
            $network = Network::tryFrom(Str::lower(trim((string) ($entry['network'] ?? ''))));

            // Solo redes del plan: el resto se ignora sin error.
            if (! $network || ! $planNetworks->contains($network)) {
                continue;
            }

            $allowed = array_map(
                fn (PostFormat $f) => $f->value,
                PostFormat::allowedFor($network)
            );

            $formats = collect($entry['formats'] ?? [])
                ->map(fn ($f) => Str::lower(trim((string) $f)))
                ->filter(fn ($f) => in_array($f, $allowed, true))
                ->unique()
                ->values()
                ->all();

            $brand->strategies()->updateOrCreate(
                ['network' => $network->value],
                [
                    'posts_per_week' => max(1, min(7, (int) ($entry['posts_per_week'] ?? 1))),
                    'preferred_formats' => $formats === [] ? $allowed : $formats,
                    'focus' => trim((string) ($entry['focus'] ?? '')),
                ]
            );

            $applied++;
        }

        $this->markApplied($brand, 'strategies');

        return $applied;
    }

    /**
     * Paleta: cuatro colores #RRGGBB en brands.palette.
     *
     * @param  array{palette: array<string, string>}  $data
     */
    public function applyPalette(Brand $brand, array $data): void
    {
        app()->instance('brand', $brand);

        $palette = collect($data['palette'] ?? [])
            ->only(['dark', 'light', 'accent', 'secondary'])
            ->map(fn ($c) => Str::upper((string) $c))
            ->all();

        abort_if($palette === [], 422, 'La propuesta no trae paleta.');

        $brand->palette = $palette;
        $brand->save();

        $this->markApplied($brand, 'palette');
    }

    /**
     * Marca onboarding_proposal.applied.{sección}=true y, con las
     * cuatro secciones aplicadas, fija onboarded_at.
     */
    public function markApplied(Brand $brand, string $section): void
    {
        $proposal = $brand->onboarding_proposal ?? [];
        $proposal['applied'] = [...($proposal['applied'] ?? []), $section => true];

        $allApplied = collect(self::SECTIONS)
            ->every(fn ($s) => (bool) ($proposal['applied'][$s] ?? false));

        $brand->forceFill([
            'onboarding_proposal' => $proposal,
            'onboarded_at' => $allApplied ? ($brand->onboarded_at ?? now()) : $brand->onboarded_at,
        ])->save();
    }
}
