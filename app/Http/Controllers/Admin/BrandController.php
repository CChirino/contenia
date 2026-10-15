<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BrandStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Models\Brand;
use App\Models\MonthlyPlan;
use App\Models\Plan;
use Database\Seeders\DefaultsSeeder;
use Illuminate\Http\RedirectResponse;
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
            ],
            'plans' => Plan::orderBy('name')->get(['id', 'name', 'price_label']),
        ]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $brand->update($request->validated());
        $this->storeLogo($request, $brand);

        return redirect()->route('brands.index');
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
