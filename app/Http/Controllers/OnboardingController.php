<?php

namespace App\Http\Controllers;

use App\Enums\BrandStatus;
use App\Enums\Network;
use App\Enums\PostFormat;
use App\Enums\PostIcon;
use App\Models\Brand;
use App\Models\BrandProfile;
use App\Models\MonthlyPlan;
use App\Models\Plan;
use App\Models\Post;
use App\Models\User;
use App\Services\Onboarding\ProposalApplier;
use App\Services\Render\TemplateRegistry;
use Database\Seeders\DefaultsSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Asistente de primeros pasos (spec 2026-10-24): recorrido A (admin,
 * crea su primera marca con IA) y B (operador, tour explicativo).
 * El estado vive en users.onboarding_state {track, step, brand_id, month}.
 */
class OnboardingController extends Controller
{
    /**
     * Decide el recorrido: operador → B; admin sin marcas (o gestor sin
     * marcas asignadas) → A; con marcas sin track elegido → selector
     * "¿Por dónde empezamos?".
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        $state = $user->onboarding_state ?? [];
        $track = $state['track'] ?? null;

        if ($track === null) {
            if (! $user->canCreateBrands()) {
                $track = 'operator';
            } elseif ($user->isManager() ? $user->brands()->doesntExist() : Brand::count() === 0) {
                $track = 'admin';
            } else {
                return Inertia::render('Onboarding/Choose', ['state' => $state]);
            }
        }

        $brand = isset($state['brand_id']) ? Brand::find($state['brand_id']) : null;

        return Inertia::render('Onboarding/Index', [
            'track' => $track,
            'step' => (int) ($state['step'] ?? 1),
            'brand' => $brand ? [
                ...$brand->only(
                    'id', 'name', 'slug', 'website_url', 'palette',
                    'requires_client_approval', 'plan_id', 'onboarding_proposal',
                ),
                'logo_url' => route('brands.logo', ['brand' => $brand->slug]),
                'plan' => $brand->plan?->only('name', 'price_label', 'posts_per_month'),
                'contacts' => $brand->contacts()->orderBy('id')->get(['id', 'name', 'email', 'whatsapp']),
                'plan_networks' => $brand->plan?->networks ?? [],
                'pillars_count' => $brand->pillars()->count(),
            ] : null,
            'plans' => Plan::orderBy('name')->get(['id', 'name', 'price_label', 'posts_per_month']),
            'state' => $state,
            'brands' => $this->tourBrands($user),
            'allowed_formats' => collect(Network::cases())
                ->mapWithKeys(fn (Network $n) => [
                    $n->value => array_map(fn (PostFormat $f) => $f->value, PostFormat::allowedFor($n)),
                ]),
        ]);
    }

    /**
     * Marcas que ve el recorrido B (nombre en la bienvenida y enlaces
     * "Ir a verlo"): las asignadas al operador; para admin, todas, con la
     * última usada primero para que los enlaces apunten a su marca habitual.
     *
     * @return Collection<int, Brand>
     */
    private function tourBrands(User $user): Collection
    {
        $query = $user->isAdmin() ? Brand::query() : $user->brands();

        return $query
            ->orderByRaw('CASE WHEN brands.id = ? THEN 0 ELSE 1 END', [$user->last_brand_id ?? 0])
            ->orderBy('brands.name')
            ->get(['brands.id', 'brands.name', 'brands.slug']);
    }

    /**
     * PATCH del estado del wizard: track, step, brand_id (con acceso) y
     * month ('YYYY-MM' para el paso de la grilla).
     */
    public function state(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'track' => ['nullable', 'in:admin,operator'],
            'step' => ['nullable', 'integer', 'min:1', 'max:6'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        if (isset($data['brand_id'])) {
            $brand = Brand::findOrFail($data['brand_id']);
            abort_unless(
                $request->user()->isAdmin() || $request->user()->brands()->whereKey($brand->id)->exists(),
                403
            );
        }

        $this->saveState($request, $data);

        return back();
    }

    /** "Saltar por ahora": marca al usuario como incorporado. */
    public function skip(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['onboarded_at' => now()])->save();

        return redirect()->route('dashboard');
    }

    /** "Guía de inicio" del menú: vuelve a mostrar el wizard conservando el paso. */
    public function restart(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['onboarded_at' => null])->save();

        return redirect()->route('onboarding.show');
    }

    /**
     * Cierra el wizard y lleva a la grilla: marca del estado o la
     * última usada por el usuario; mes del estado o el siguiente.
     */
    public function complete(Request $request): RedirectResponse|Response
    {
        $user = $request->user();
        $state = $user->onboarding_state ?? [];

        $brand = isset($state['brand_id'])
            ? Brand::find($state['brand_id'])
            : $this->tourBrands($user)->first();

        if ($brand === null) {
            // Quien puede crear marcas vuelve al wizard (track de
            // creación); el operador sin marcas ve la página amable
            // en vez del antiguo 422 "No hay marca a la que ir.".
            if ($user->canCreateBrands()) {
                return redirect()->route('onboarding.show');
            }

            return Inertia::render('Onboarding/NoBrands');
        }

        $user->forceFill(['onboarded_at' => now()])->save();

        [$year, $month] = isset($state['month'])
            ? explode('-', $state['month'])
            : [now('America/Caracas')->addMonth()->year, now('America/Caracas')->addMonth()->month];

        return redirect()->route('plans.show', [
            'brand' => $brand->slug,
            'year' => (int) $year,
            'month' => (int) $month,
        ]);
    }

    /**
     * Recorrido A paso 2: crea la primera marca (admin o gestor)
     * reutilizando DefaultsSeeder::forBrand. Si el estado ya tiene
     * brand_id, actualiza.
     */
    public function storeBrand(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canCreateBrands(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'requires_client_approval' => ['boolean'],
        ]);

        $state = $request->user()->onboarding_state ?? [];
        $brand = isset($state['brand_id']) ? Brand::find($state['brand_id']) : null;

        if ($brand) {
            $brand->update($data);
        } else {
            // Paleta temporal neutra: la propuesta IA la reemplaza en el paso 3.
            $brand = new Brand($data);
            $brand->slug = $this->uniqueSlug($brand->name);
            $brand->status = BrandStatus::Active;
            $brand->palette = [
                'dark' => '#14122B',
                'light' => '#F5F4FF',
                'accent' => '#4F46E5',
                'secondary' => '#8B5CF6',
            ];
            $brand->save();

            DefaultsSeeder::forBrand($brand);
        }

        $request->user()->brands()->syncWithoutDetaching([$brand->id]);
        $this->saveState($request, ['brand_id' => $brand->id, 'step' => 3]);

        return back()->with('success', "Marca {$brand->name} lista.");
    }

    /**
     * Estado para el polling de los pasos 3 (propuesta IA) y 6 (grilla).
     */
    public function status(Request $request): JsonResponse
    {
        $state = $request->user()->onboarding_state ?? [];
        $brand = isset($state['brand_id']) ? Brand::find($state['brand_id']) : null;

        $monthParam = $request->query('month', $state['month'] ?? null);
        $plan = null;
        if ($brand && $monthParam && preg_match('/^(\d{4})-(\d{2})$/', $monthParam, $m)) {
            $plan = MonthlyPlan::withoutGlobalScopes()
                ->where('brand_id', $brand->id)
                ->where('year', (int) $m[1])
                ->where('month', (int) $m[2])
                ->withCount('posts')
                ->first();
        }

        $proposal = $brand?->onboarding_proposal ?? null;

        return response()->json([
            'pending' => $brand !== null && Cache::has("onboarding:{$brand->id}"),
            'proposal' => $proposal,
            'applied' => $proposal['applied'] ?? [],
            'pillars_count' => $brand?->pillars()->count() ?? 0,
            'contacts_count' => $brand?->contacts()->count() ?? 0,
            'requires_client_approval' => (bool) ($brand?->requires_client_approval ?? false),
            'plan' => [
                'generating_at' => $plan?->generating_at?->toIso8601String(),
                'posts_count' => $plan?->posts_count ?? 0,
                'last_error' => $plan?->last_error,
            ],
        ]);
    }

    /**
     * "Aplicar todo": las cuatro secciones de la propuesta en una sola
     * transacción (pillars con replace).
     */
    public function applyAll(Request $request, ProposalApplier $applier): RedirectResponse
    {
        $brand = $this->stateBrand($request);
        abort_unless($brand !== null, 422, 'No hay marca en el asistente.');

        $applier->applyAll($brand);

        return back()->with('success', 'Propuesta aplicada: perfil, pilares, estrategias y paleta.');
    }

    /**
     * Configuración manual mínima (sin IA): perfil básico + 3 pilares
     * genéricos solo si la marca aún no tiene pilares.
     */
    public function manualSetup(Request $request, ProposalApplier $applier): RedirectResponse
    {
        $brand = $this->stateBrand($request);
        abort_unless($brand !== null, 422, 'No hay marca en el asistente.');

        // Quien configura la marca queda asignado a ella (gestor incluido).
        $request->user()->brands()->syncWithoutDetaching([$brand->id]);

        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:300'],
            'tone_of_voice' => ['nullable', 'string', 'max:255'],
            'audience' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($brand, $data) {
            app()->instance('brand', $brand);

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
            ]);

            if ($brand->pillars()->count() === 0) {
                foreach ([
                    ['name' => 'Educativo', 'weight' => 40],
                    ['name' => 'Marca', 'weight' => 30],
                    ['name' => 'Comunidad', 'weight' => 30],
                ] as $pillar) {
                    $brand->pillars()->create([
                        'name' => $pillar['name'],
                        'description' => '',
                        'weight' => $pillar['weight'],
                    ]);
                }
            }

            // Si había una propuesta, se marcan como aplicadas las
            // secciones que cubre la configuración manual.
            $proposal = $brand->onboarding_proposal;
            if ($proposal !== null) {
                $proposal['applied'] = [
                    ...($proposal['applied'] ?? []),
                    'profile' => true,
                    'pillars' => true,
                ];
                $brand->forceFill(['onboarding_proposal' => $proposal])->save();
            }
        });

        return back()->with('success', 'Perfil y pilares configurados.');
    }

    /**
     * Vista previa real: la plantilla instagram-quote con un post de
     * muestra en memoria y la paleta/logo actuales de la marca del
     * estado. Sin persistir nada (se usa en un <iframe> del paso 4).
     */
    public function preview(Request $request): Response
    {
        $brand = $this->stateBrand($request);
        abort_unless($brand !== null, 404);

        app()->instance('brand', $brand);
        Inertia::setRootView('render');

        $template = TemplateRegistry::for(Network::Instagram, PostFormat::Image);
        $profile = BrandProfile::current();

        return Inertia::render('Render/Frame', [
            'template' => [
                'name' => $template->name,
                'component' => $template->component,
                'width' => $template->width,
                'height' => $template->height,
                'photo_height' => $template->photoHeight,
            ],
            'frame' => 1,
            'framesTotal' => 1,
            'post' => [
                'network' => Network::Instagram->value,
                'hook' => "Hola, somos {$brand->name}",
                'copy' => 'Tu primera publicación en Contenia, diseñada con la identidad de tu marca.',
                'design_payload' => [
                    'slides' => [[
                        'title' => "Hola, somos {$brand->name}",
                        'body' => 'Tu primera publicación en Contenia, con la identidad de tu marca.',
                    ]],
                ],
                'content_pillar' => ['name' => 'Bienvenida'],
            ],
            'backgroundUrl' => null,
            'icon' => PostIcon::default()->value,
            'logoUrl' => route('brands.logo', ['brand' => $brand->slug]),
            'wordmark' => $brand->name.'.',
            'palette' => $brand->palette,
            'brand' => [
                'url' => $profile->url,
                'cta' => $profile->cta,
            ],
        ]);
    }

    private function stateBrand(Request $request): ?Brand
    {
        $brandId = $request->user()->onboarding_state['brand_id'] ?? null;

        return $brandId ? Brand::find($brandId) : null;
    }

    private function saveState(Request $request, array $data): void
    {
        $state = [...($request->user()->onboarding_state ?? [])];

        foreach (['track', 'step', 'brand_id', 'month'] as $key) {
            if (array_key_exists($key, $data)) {
                if ($data[$key] === null) {
                    unset($state[$key]);
                } else {
                    $state[$key] = $data[$key];
                }
            }
        }

        $request->user()->forceFill(['onboarding_state' => $state])->save();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'brand';
        $slug = $base;
        $i = 2;

        while (Brand::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
