<?php

namespace App\Services\Onboarding;

/**
 * Reglas de validación compartidas por los endpoints por sección
 * (OnboardingApplyController) y el "Aplicar todo" del wizard
 * (OnboardingController::applyAll las usa con 'sometimes').
 */
class ProposalRules
{
    /** @return array<string, array<int, mixed>> */
    public static function profile(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:300'],
            'tone_of_voice' => ['nullable', 'string', 'max:255'],
            'audience' => ['nullable', 'string', 'max:255'],
            'cta' => ['nullable', 'string', 'max:255'],
            'base_hashtags' => ['nullable', 'array', 'max:8'],
            'base_hashtags.*' => ['string', 'max:30'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public static function pillars(): array
    {
        return [
            'pillars' => ['required', 'array', 'min:1', 'max:6'],
            'pillars.*.name' => ['required', 'string', 'max:100'],
            'pillars.*.description' => ['nullable', 'string', 'max:500'],
            'pillars.*.weight' => ['required', 'integer', 'min:1', 'max:100'],
            'replace' => ['boolean'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public static function strategies(): array
    {
        return [
            'strategies' => ['required', 'array', 'min:1'],
            'strategies.*.network' => ['required', 'string'],
            'strategies.*.posts_per_week' => ['required', 'integer', 'min:1', 'max:7'],
            'strategies.*.formats' => ['nullable', 'array'],
            'strategies.*.formats.*' => ['string'],
            'strategies.*.focus' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public static function palette(): array
    {
        $hex = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'palette' => ['required', 'array'],
            'palette.primary' => ['required', 'array', 'size:3'],
            'palette.primary.*' => $hex,
            'palette.secondary' => ['nullable', 'array', 'max:3'],
            'palette.secondary.*' => $hex,
        ];
    }

    /**
     * Todas las secciones juntas, cada regla con 'sometimes': para el
     * "Aplicar todo" del wizard, que acepta cualquier subconjunto.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function sometimes(): array
    {
        $all = array_merge(
            self::profile(),
            self::pillars(),
            self::strategies(),
            self::palette(),
        );

        return array_map(
            fn (array $rules) => ['sometimes', ...$rules],
            $all
        );
    }
}
