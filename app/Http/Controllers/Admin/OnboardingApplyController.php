<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
 * Las rutas admin no pasan por SetActiveBrand: cada método fija la
 * marca del route-binding como activa para que BelongsToBrand asigne
 * brand_id y los scopes filtren correctamente.
 */
class OnboardingApplyController extends Controller
{
    private const SECTIONS = ['profile', 'pillars', 'strategies', 'palette'];

    /**
     * Perfil: escribe description/tone/audience/cta/hashtags en
     * brand_profile (updateOrCreate del perfil de esa marca).
     */
    public function profile(Request $request, Brand $brand): RedirectResponse
    {
        app()->instance('brand', $brand);

        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:300'],
            'tone_of_voice' => ['nullable', 'string', 'max:255'],
            'audience' => ['nullable', 'string', 'max:255'],
            'cta' => ['nullable', 'string', 'max:255'],
            'base_hashtags' => ['nullable', 'array', 'max:8'],
            'base_hashtags.*' => ['string', 'max:30'],
        ]);

        $hashtags = collect($data['base_hashtags'] ?? [])
            ->map(fn ($t) => '#'.ltrim(trim((string) $t), '#'))
            ->filter(fn ($t) => $t !== '#')
            ->unique()
            ->take(8)
            ->values()
            ->all();

        DB::transaction(function () use ($brand, $data, $hashtags) {
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
        });

        return back()->with('success', 'Perfil aplicado a la marca.');
    }

    /**
     * Pilares: los pesos deben sumar 100. Si la marca ya tiene pilares
     * hace falta replace=true (el cliente confirma); si no, se crean
     * directamente. Los posts apuntan a pilar con nullOnDelete.
     */
    public function pillars(Request $request, Brand $brand): RedirectResponse
    {
        app()->instance('brand', $brand);

        $data = $request->validate([
            'pillars' => ['required', 'array', 'min:1', 'max:6'],
            'pillars.*.name' => ['required', 'string', 'max:100'],
            'pillars.*.description' => ['nullable', 'string', 'max:500'],
            'pillars.*.weight' => ['required', 'integer', 'min:1', 'max:100'],
            'replace' => ['boolean'],
        ]);

        $sum = collect($data['pillars'])->sum('weight');
        if ($sum !== 100) {
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

        DB::transaction(function () use ($brand, $data) {
            $brand->pillars()->delete();

            foreach ($data['pillars'] as $pillar) {
                $brand->pillars()->create([
                    'name' => trim($pillar['name']),
                    'description' => trim((string) ($pillar['description'] ?? '')),
                    'weight' => (int) $pillar['weight'],
                ]);
            }

            $this->markApplied($brand, 'pillars');
        });

        return back()->with('success', 'Pilares aplicados a la marca.');
    }

    /**
     * Estrategias por red: updateOrCreate por red, solo para redes del
     * plan de la marca (las demás se ignoran). Formatos recortados a
     * PostFormat::allowedFor; si quedan vacíos se usan los permitidos.
     */
    public function strategies(Request $request, Brand $brand): RedirectResponse
    {
        app()->instance('brand', $brand);

        $data = $request->validate([
            'strategies' => ['required', 'array', 'min:1'],
            'strategies.*.network' => ['required', 'string'],
            'strategies.*.posts_per_week' => ['required', 'integer', 'min:1', 'max:7'],
            'strategies.*.formats' => ['nullable', 'array'],
            'strategies.*.formats.*' => ['string'],
            'strategies.*.focus' => ['nullable', 'string', 'max:500'],
        ]);

        $planNetworks = collect($brand->plan?->networks ?? [])
            ->map(fn ($n) => Network::tryFrom((string) $n))
            ->filter()
            ->values();

        $applied = 0;

        DB::transaction(function () use ($brand, $data, $planNetworks, &$applied) {
            foreach ($data['strategies'] as $entry) {
                $network = Network::tryFrom(Str::lower(trim((string) $entry['network'])));

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
                        'posts_per_week' => max(1, min(7, (int) $entry['posts_per_week'])),
                        'preferred_formats' => $formats === [] ? $allowed : $formats,
                        'focus' => trim((string) ($entry['focus'] ?? '')),
                    ]
                );

                $applied++;
            }

            $this->markApplied($brand, 'strategies');
        });

        return back()->with('success', "Estrategias aplicadas ({$applied} redes del plan).");
    }

    /**
     * Paleta: cuatro colores #RRGGBB en brands.palette.
     */
    public function palette(Request $request, Brand $brand): RedirectResponse
    {
        app()->instance('brand', $brand);

        $hex = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        $data = $request->validate([
            'palette' => ['required', 'array'],
            'palette.dark' => $hex,
            'palette.light' => $hex,
            'palette.accent' => $hex,
            'palette.secondary' => $hex,
        ]);

        DB::transaction(function () use ($brand, $data) {
            $brand->palette = collect($data['palette'])
                ->only(['dark', 'light', 'accent', 'secondary'])
                ->map(fn ($c) => Str::upper($c))
                ->all();
            $brand->save();

            $this->markApplied($brand, 'palette');
        });

        return back()->with('success', 'Paleta aplicada a la marca.');
    }

    /**
     * Marca onboarding_proposal.applied.{sección}=true y, con las
     * cuatro secciones aplicadas, fija onboarded_at.
     */
    private function markApplied(Brand $brand, string $section): void
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
