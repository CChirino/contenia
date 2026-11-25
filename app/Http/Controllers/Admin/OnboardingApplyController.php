<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\Onboarding\ProposalApplier;
use App\Services\Onboarding\ProposalRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Aplica por secciones la propuesta de onboarding (borrador en
 * brands.onboarding_proposal) a las tablas reales. La lógica vive en
 * ProposalApplier, compartida con el wizard (/onboarding/apply-all).
 */
class OnboardingApplyController extends Controller
{
    public function profile(Request $request, Brand $brand, ProposalApplier $applier): RedirectResponse
    {
        $data = $request->validate(ProposalRules::profile());

        DB::transaction(fn () => $applier->applyProfile($brand, $data));

        return back()->with('success', 'Perfil aplicado a la marca.');
    }

    public function pillars(Request $request, Brand $brand, ProposalApplier $applier): RedirectResponse
    {
        $data = $request->validate(ProposalRules::pillars());

        DB::transaction(fn () => $applier->applyPillars($brand, $data));

        return back()->with('success', 'Pilares aplicados a la marca.');
    }

    public function strategies(Request $request, Brand $brand, ProposalApplier $applier): RedirectResponse
    {
        $data = $request->validate(ProposalRules::strategies());

        $applied = DB::transaction(fn () => $applier->applyStrategies($brand, $data));

        return back()->with('success', "Estrategias aplicadas ({$applied} redes del plan).");
    }

    public function palette(Request $request, Brand $brand, ProposalApplier $applier): RedirectResponse
    {
        $data = $request->validate(ProposalRules::palette());

        DB::transaction(fn () => $applier->applyPalette($brand, $data));

        return back()->with('success', 'Paleta aplicada a la marca.');
    }

    /**
     * Usa la foto de perfil de la red social como logo. La URL sale de la
     * propuesta guardada (nunca del cliente): se descarga, se valida como
     * imagen y se guarda como PNG ≤512 px en la ruta del logo de la marca.
     */
    public function logo(Brand $brand, ProposalApplier $applier): RedirectResponse
    {
        $url = $brand->onboarding_proposal['logo_candidate_url'] ?? null;
        abort_unless(filled($url), 422, 'La propuesta no tiene foto de perfil para usar como logo.');

        $fail = fn () => throw ValidationException::withMessages([
            'logo' => 'No se pudo descargar la foto de perfil. Sube el logo a mano.',
        ]);

        try {
            $bytes = Http::timeout(10)->get($url)->throw()->body();
        } catch (Throwable) {
            $fail();
        }

        $image = @getimagesizefromstring($bytes) ? @imagecreatefromstring($bytes) : false;
        if ($image === false) {
            $fail();
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, 512 / max($width, $height));
        $resized = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));

        ob_start();
        imagepng($resized);
        $png = (string) ob_get_clean();
        imagedestroy($image);
        imagedestroy($resized);

        if (strlen($png) > 1024 * 1024) {
            $fail();
        }

        $path = "brands/{$brand->id}/logo.png";
        Storage::disk('local')->put($path, $png);
        $brand->forceFill(['logo_path' => $path])->save();
        $applier->markApplied($brand->fresh(), 'logo');

        return back()->with('success', 'Foto de perfil aplicada como logo.');
    }
}
