<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\Onboarding\ProposalApplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Aplica por secciones la propuesta de onboarding (borrador en
 * brands.onboarding_proposal) a las tablas reales. La lógica vive en
 * ProposalApplier, compartida con el wizard (/onboarding/apply-all).
 */
class OnboardingApplyController extends Controller
{
    public function profile(Request $request, Brand $brand, ProposalApplier $applier): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:300'],
            'tone_of_voice' => ['nullable', 'string', 'max:255'],
            'audience' => ['nullable', 'string', 'max:255'],
            'cta' => ['nullable', 'string', 'max:255'],
            'base_hashtags' => ['nullable', 'array', 'max:8'],
            'base_hashtags.*' => ['string', 'max:30'],
        ]);

        DB::transaction(fn () => $applier->applyProfile($brand, $data));

        return back()->with('success', 'Perfil aplicado a la marca.');
    }

    public function pillars(Request $request, Brand $brand, ProposalApplier $applier): RedirectResponse
    {
        $data = $request->validate([
            'pillars' => ['required', 'array', 'min:1', 'max:6'],
            'pillars.*.name' => ['required', 'string', 'max:100'],
            'pillars.*.description' => ['nullable', 'string', 'max:500'],
            'pillars.*.weight' => ['required', 'integer', 'min:1', 'max:100'],
            'replace' => ['boolean'],
        ]);

        DB::transaction(fn () => $applier->applyPillars($brand, $data));

        return back()->with('success', 'Pilares aplicados a la marca.');
    }

    public function strategies(Request $request, Brand $brand, ProposalApplier $applier): RedirectResponse
    {
        $data = $request->validate([
            'strategies' => ['required', 'array', 'min:1'],
            'strategies.*.network' => ['required', 'string'],
            'strategies.*.posts_per_week' => ['required', 'integer', 'min:1', 'max:7'],
            'strategies.*.formats' => ['nullable', 'array'],
            'strategies.*.formats.*' => ['string'],
            'strategies.*.focus' => ['nullable', 'string', 'max:500'],
        ]);

        $applied = DB::transaction(fn () => $applier->applyStrategies($brand, $data));

        return back()->with('success', "Estrategias aplicadas ({$applied} redes del plan).");
    }

    public function palette(Request $request, Brand $brand, ProposalApplier $applier): RedirectResponse
    {
        $hex = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        $data = $request->validate([
            'palette' => ['required', 'array'],
            'palette.dark' => $hex,
            'palette.light' => $hex,
            'palette.accent' => $hex,
            'palette.secondary' => $hex,
        ]);

        DB::transaction(fn () => $applier->applyPalette($brand, $data));

        return back()->with('success', 'Paleta aplicada a la marca.');
    }
}
