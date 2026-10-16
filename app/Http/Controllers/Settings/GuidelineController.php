<?php

namespace App\Http\Controllers\Settings;

use App\Enums\GuidelineSource;
use App\Enums\GuidelineStatus;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandGuideline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pautas aprendidas de la marca (spec §8.2): la IA propone (pending /
 * pending_retire) y el operador confirma. Solo las `active` entran al
 * prompt. Las rutas cuelgan de /b/{brand}/settings con el middleware
 * `brand`, así que el scope ya filtra por la marca activa.
 */
class GuidelineController extends Controller
{
    public function index(): Response
    {
        $guidelines = BrandGuideline::orderByDesc('weight')
            ->orderByDesc('updated_at')
            ->orderBy('id')
            ->get()
            ->map(fn (BrandGuideline $g) => [
                'id' => $g->id,
                'text' => $g->text,
                'source' => $g->source->value,
                'status' => $g->status->value,
                'weight' => $g->weight,
                'evidence_count' => $g->evidence_count,
            ])
            ->values();

        return Inertia::render('Settings/Guidelines', [
            'guidelines' => $guidelines,
        ]);
    }

    /**
     * Pauta manual del operador: entra activa directamente.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:200'],
        ]);

        BrandGuideline::create([
            'text' => trim($data['text']),
            'source' => GuidelineSource::Operator,
            'status' => GuidelineStatus::Active,
            'weight' => 1,
            'evidence_count' => 1,
        ]);

        return redirect()->route('settings.guidelines.index');
    }

    /** pending → active */
    public function activate(Brand $brand, BrandGuideline $guideline): RedirectResponse
    {
        $this->transition($guideline, [GuidelineStatus::Pending], GuidelineStatus::Active);

        return redirect()->route('settings.guidelines.index');
    }

    /** pending → discarded */
    public function discard(Brand $brand, BrandGuideline $guideline): RedirectResponse
    {
        $this->transition($guideline, [GuidelineStatus::Pending], GuidelineStatus::Discarded);

        return redirect()->route('settings.guidelines.index');
    }

    /** active|pending_retire → retired */
    public function retire(Brand $brand, BrandGuideline $guideline): RedirectResponse
    {
        $this->transition(
            $guideline,
            [GuidelineStatus::Active, GuidelineStatus::PendingRetire],
            GuidelineStatus::Retired
        );

        return redirect()->route('settings.guidelines.index');
    }

    /** pending_retire → active (mantener la pauta) */
    public function keep(Brand $brand, BrandGuideline $guideline): RedirectResponse
    {
        $this->transition($guideline, [GuidelineStatus::PendingRetire], GuidelineStatus::Active);

        return redirect()->route('settings.guidelines.index');
    }

    /**
     * Edita el texto de una pauta viva (pending, active o
     * pending_retire); las archivadas son de solo lectura.
     */
    public function update(Request $request, Brand $brand, BrandGuideline $guideline): RedirectResponse
    {
        abort_unless(
            in_array($guideline->status, [
                GuidelineStatus::Pending,
                GuidelineStatus::Active,
                GuidelineStatus::PendingRetire,
            ], true),
            422,
            'No se puede editar una pauta archivada.'
        );

        $data = $request->validate([
            'text' => ['required', 'string', 'max:200'],
        ]);

        $guideline->update(['text' => trim($data['text'])]);

        return redirect()->route('settings.guidelines.index');
    }

    /**
     * Borrado físico solo para pautas que nunca llegaron a activarse:
     * pending o discarded. Las demás se archivan con retire.
     */
    public function destroy(Brand $brand, BrandGuideline $guideline): RedirectResponse
    {
        abort_unless(
            in_array($guideline->status, [
                GuidelineStatus::Pending,
                GuidelineStatus::Discarded,
            ], true),
            422,
            'Solo se pueden eliminar pautas pendientes o descartadas.'
        );

        $guideline->delete();

        return redirect()->route('settings.guidelines.index');
    }

    /**
     * Aplica una transición de estado o devuelve 422 si el estado
     * actual no lo permite.
     *
     * @param  array<int, GuidelineStatus>  $from
     */
    private function transition(BrandGuideline $guideline, array $from, GuidelineStatus $to): void
    {
        abort_unless(
            in_array($guideline->status, $from, true),
            422,
            "No se puede pasar de {$guideline->status->value} a {$to->value}."
        );

        $guideline->update(['status' => $to]);
    }
}
