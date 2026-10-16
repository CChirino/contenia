<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BrandStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Jobs\OnboardBrand;
use App\Models\Brand;
use App\Models\MonthlyPlan;
use App\Models\Plan;
use Database\Seeders\DefaultsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(): Response
    {
        $now = now('America/Caracas');

        $brands = Brand::with('plan:id,name,price_label')
            ->withCount('users')
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

        $this->storeLogo($request, $brand);
        DefaultsSeeder::forBrand($brand);

        return redirect()->route('brands.index');
    }

    public function edit(Brand $brand): Response
    {
        return Inertia::render('Admin/Brands/Form', [
            'brand' => [
                ...$brand->only('id', 'name', 'slug', 'website_url', 'palette', 'requires_client_approval', 'plan_id'),
                'logo_url' => route('brands.logo', ['brand' => $brand->slug]),
                'contacts' => $brand->contacts()->orderBy('id')->get(['id', 'name', 'email', 'whatsapp']),
                'onboarding_proposal' => $brand->onboarding_proposal,
                'onboarded_at' => $brand->onboarded_at?->toIso8601String(),
            ],
            'plans' => Plan::orderBy('name')->get(['id', 'name', 'price_label']),
            'onboarding_pending' => Cache::has("onboarding:{$brand->id}"),
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

        abort_unless(
            filled($brand->website_url),
            422,
            'La marca necesita un sitio web para generar la propuesta con IA.'
        );

        // Posts de ejemplo separados por líneas en blanco, máx. 5.
        $posts = collect(preg_split('/\R\s*\R/u', (string) ($data['sample_posts'] ?? '')) ?: [])
            ->map(fn ($t) => trim($t))
            ->filter()
            ->take(5)
            ->values()
            ->all();

        $samples = array_filter([
            'social_handle' => $data['social_handle'] ?? null,
            'posts' => $posts,
        ]);

        Cache::put("onboarding:{$brand->id}", true, 600);
        OnboardBrand::dispatch($brand, $samples);

        return back()->with('success', 'Generando propuesta… (tarda ~1 min).');
    }

    public function toggle(Brand $brand): RedirectResponse
    {
        $brand->status = $brand->status === BrandStatus::Active
            ? BrandStatus::Paused
            : BrandStatus::Active;
        $brand->save();

        return redirect()->route('brands.index');
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
