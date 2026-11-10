<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BrandStatus;
use App\Enums\Network;
use App\Enums\PostFormat;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Jobs\DistillFeedback;
use App\Jobs\OnboardBrand;
use App\Models\Brand;
use App\Models\MonthlyPlan;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\DefaultsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(Request $request): Response
    {
        $now = now('America/Caracas');
        $user = $request->user();

        // El gestor solo ve sus marcas asignadas; el admin las ve todas.
        $brands = Brand::with('plan:id,name,price_label')
            ->withCount('users')
            ->when(
                ! $user->isAdmin(),
                fn ($query) => $query->whereHas(
                    'users',
                    fn ($users) => $users->where('users.id', $user->id)
                )
            )
            ->orderBy('name')
            ->get()
            ->map(function (Brand $brand) use ($now) {
                $postsThisMonth = MonthlyPlan::where('brand_id', $brand->id)
                    ->where('year', $now->year)
                    ->where('month', $now->month)
                    ->withCount('posts')
                    ->first()?->posts_count ?? 0;

                return [
                    ...$brand->only('id', 'name', 'slug', 'website_url', 'status', 'requires_client_approval'),
                    'status' => $brand->status->value,
                    'plan' => $brand->plan?->only('name', 'price_label'),
                    'operators_count' => $brand->users_count,
                    'posts_this_month' => $postsThisMonth,
                    'logo_url' => route('brands.logo', ['brand' => $brand->slug]),
                ];
            });

        return Inertia::render('Admin/Brands/Index', ['brands' => $brands]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Brands/Form', [
            'brand' => null,
            'plans' => Plan::orderBy('name')->get(['id', 'name', 'price_label']),
        ]);
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $brand = new Brand($data);
        $brand->slug = $this->uniqueSlug($brand->name);
        $brand->status = BrandStatus::Active;
        $brand->save();

        // El creador queda asignado: un gestor solo gestiona sus marcas.
        $brand->users()->syncWithoutDetaching([$request->user()->id]);

        $this->storeLogo($request, $brand);
        DefaultsSeeder::forBrand($brand);

        return redirect()->route('brands.index');
    }

    public function edit(Request $request, Brand $brand): Response
    {
        return Inertia::render('Admin/Brands/Form', [
            'brand' => [
                ...$brand->only('id', 'name', 'slug', 'website_url', 'palette', 'requires_client_approval', 'plan_id'),
                'logo_url' => route('brands.logo', ['brand' => $brand->slug]),
                'contacts' => $brand->contacts()->orderBy('id')->get(['id', 'name', 'email', 'whatsapp']),
                'onboarding_proposal' => $brand->onboarding_proposal,
                'onboarded_at' => $brand->onboarded_at?->toIso8601String(),
                'plan_networks' => $brand->plan?->networks ?? [],
                'pillars_count' => $brand->pillars()->count(),
            ],
            'plans' => Plan::orderBy('name')->get(['id', 'name', 'price_label']),
            'onboarding_pending' => Cache::has("onboarding:{$brand->id}"),
            // Operadores vinculables (tarjeta "Operadores asignados"): solo
            // si quien entra puede gestionar la marca.
            'operators' => $request->user()->can('manage', $brand)
                ? $this->operatorOptions($brand)
                : null,
            // Formatos permitidos por red (espejo de PostFormat::allowedFor).
            'allowed_formats' => collect(Network::cases())
                ->mapWithKeys(fn (Network $n) => [
                    $n->value => array_map(fn (PostFormat $f) => $f->value, PostFormat::allowedFor($n)),
                ]),
        ]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $brand->update($request->validated());
        $this->storeLogo($request, $brand);

        return redirect()->route('brands.index');
    }

    /**
     * Lanza el onboarding con IA: encola OnboardBrand y marca la marca
     * como "generando" en caché (la UI hace polling a este flag).
     */
    public function runOnboarding(Request $request, Brand $brand): RedirectResponse
    {
        $data = $request->validate([
            'sample_posts' => ['nullable', 'string', 'max:5000'],
            'social_handle' => ['nullable', 'string', 'max:100'],
        ]);

        // Posts de ejemplo separados por líneas en blanco, máx. 5.
        $posts = collect(preg_split('/\R\s*\R/u', (string) ($data['sample_posts'] ?? '')) ?: [])
            ->map(fn ($t) => trim($t))
            ->filter()
            ->take(5)
            ->values()
            ->all();

        // Basta una fuente: web (o perfil social en ese campo), usuario
        // social o posts de ejemplo — marcas sin web también se proponen.
        if (blank($brand->website_url) && blank($data['social_handle'] ?? null) && $posts === []) {
            throw ValidationException::withMessages([
                'sample_posts' => 'Indica un sitio web o red social, un usuario social o pega posts de ejemplo.',
            ]);
        }

        $samples = array_filter([
            'social_handle' => $data['social_handle'] ?? null,
            'posts' => $posts,
        ]);

        Cache::put("onboarding:{$brand->id}", true, 600);
        OnboardBrand::dispatch($brand, $samples);

        return back()->with('success', 'Generando propuesta… (tarda ~1 min).');
    }

    /**
     * Destila a mano el feedback del último mes en pautas de la marca.
     */
    public function distillGuidelines(Brand $brand): RedirectResponse
    {
        DistillFeedback::dispatch($brand, now()->subMonth(), now());

        return back()->with('success', 'Destilando el feedback del último mes en pautas…');
    }

    /**
     * Sincroniza los operadores de la marca desde la UI de checkboxes:
     * {user_ids: []} reemplaza SOLO los usuarios con role=operator (los
     * gestores/admins asignados se conservan, vía /users del admin).
     */
    public function syncOperators(Request $request, Brand $brand): RedirectResponse
    {
        $data = $request->validate([
            'user_ids' => ['present', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $operators = User::whereIn('id', $data['user_ids'])
            ->where('role', UserRole::Operator)
            ->pluck('id');

        abort_if(
            $operators->count() !== count($data['user_ids']),
            422,
            'Solo se pueden vincular usuarios con rol operador.'
        );

        $nonOperators = $brand->users()
            ->where('role', '!=', UserRole::Operator->value)
            ->pluck('users.id');

        $brand->users()->sync($operators->merge($nonOperators)->all());

        return back()->with('success', 'Operadores de la marca actualizados.');
    }

    /**
     * Vincula un operador existente a la marca (no crea usuarios).
     */
    public function attachOperator(Brand $brand, User $user): RedirectResponse
    {
        abort_unless($user->isOperator(), 422, 'Solo se pueden vincular operadores.');

        $brand->users()->syncWithoutDetaching([$user->id]);

        return back()->with('success', "{$user->name} vinculado a {$brand->name}.");
    }

    /**
     * Desvincula un usuario de la marca.
     */
    public function detachOperator(Brand $brand, User $user): RedirectResponse
    {
        $brand->users()->detach($user->id);

        return back()->with('success', "{$user->name} desvinculado de {$brand->name}.");
    }

    public function toggle(Brand $brand): RedirectResponse
    {
        $brand->status = $brand->status === BrandStatus::Active
            ? BrandStatus::Paused
            : BrandStatus::Active;
        $brand->save();

        return redirect()->route('brands.index');
    }

    /**
     * Usuarios con role=operator para la tarjeta "Operadores asignados"
     * del formulario de marca, con el flag de asignación actual.
     *
     * @return array<int, array{id: int, name: string, email: string, assigned: bool}>
     */
    private function operatorOptions(Brand $brand): array
    {
        $assigned = $brand->users()->pluck('users.id');

        return User::where('role', UserRole::Operator)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'assigned' => $assigned->contains($user->id),
            ])
            ->all();
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

    private function storeLogo(StoreBrandRequest|UpdateBrandRequest $request, Brand $brand): void
    {
        if (! $request->hasFile('logo')) {
            return;
        }

        $file = $request->file('logo');
        $ext = strtolower($file->getClientOriginalExtension());
        $path = "brands/{$brand->id}/logo.{$ext}";

        Storage::disk('local')->putFileAs("brands/{$brand->id}", $file, "logo.{$ext}");
        $brand->forceFill(['logo_path' => $path])->save();
    }
}
