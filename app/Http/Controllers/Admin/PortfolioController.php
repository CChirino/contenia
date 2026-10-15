<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuotaAction;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandUsage;
use App\Models\LlmLog;
use App\Models\Post;
use App\Models\ReviewRequest;
use App\Services\Quota\QuotaGuard;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cartera: consumo por marca del mes seleccionado y extensiones de cuota.
 */
class PortfolioController extends Controller
{
    public function index(Request $request): Response
    {
        [$year, $month] = $this->period($request);
        $quota = app(QuotaGuard::class);

        // Agregados de LLM del mes (por fecha de la llamada).
        $llm = LlmLog::withoutGlobalScopes()
            ->selectRaw('brand_id, sum(cost_usd) as cost, sum(input_tokens) as tin, sum(output_tokens) as tout')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->groupBy('brand_id')
            ->get()
            ->keyBy('brand_id');

        $usages = BrandUsage::withoutGlobalScopes()
            ->where('year', $year)->where('month', $month)
            ->get()
            ->keyBy('brand_id');

        $lastReviews = ReviewRequest::withoutGlobalScopes()
            ->selectRaw('brand_id, max(created_at) as last_at')
            ->groupBy('brand_id')
            ->pluck('last_at', 'brand_id');

        $lastPublished = Post::withoutGlobalScopes()
            ->selectRaw('brand_id, max(scheduled_at) as last_at')
            ->where('status', 'published')
            ->groupBy('brand_id')
            ->pluck('last_at', 'brand_id');

        $rows = Brand::with('plan')->orderBy('name')->get()
            ->map(function (Brand $brand) use ($quota, $year, $month, $llm, $usages, $lastReviews, $lastPublished) {
                $usage = $usages->get($brand->id);
                $agg = $llm->get($brand->id);

                $actions = [];
                foreach (QuotaAction::cases() as $action) {
                    $actions[] = [
                        'action' => $action->value,
                        'used' => (int) ($usage?->{$action->value} ?? 0),
                        'limit' => $quota->limit($brand, $action, $year, $month),
                    ];
                }

                return [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                    'logo_url' => route('brands.logo', ['brand' => $brand->slug]),
                    'plan' => $brand->plan?->only('name', 'price_label'),
                    'status' => $brand->status->value,
                    'actions' => $actions,
                    'extras' => $usage?->only('extra_posts', 'extra_regenerations', 'extra_image_refreshes')
                        ?? ['extra_posts' => 0, 'extra_regenerations' => 0, 'extra_image_refreshes' => 0],
                    'cost_usd' => round((float) ($agg->cost ?? 0), 2),
                    'tokens' => (int) (($agg->tin ?? 0) + ($agg->tout ?? 0)),
                    'image_calls' => (int) ($usage?->image_calls ?? 0),
                    'last_review_at' => $lastReviews[$brand->id] ?? null,
                    'last_published_at' => $lastPublished[$brand->id] ?? null,
                ];
            });

        return Inertia::render('Admin/Portfolio/Index', [
            'period' => sprintf('%04d-%02d', $year, $month),
            'rows' => $rows,
        ]);
    }

    /**
     * Reemplaza las extensiones de cupo del mes seleccionado.
     */
    public function extend(Request $request, Brand $brand): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'extra_posts' => ['nullable', 'integer', 'min:0'],
            'extra_regenerations' => ['nullable', 'integer', 'min:0'],
            'extra_image_refreshes' => ['nullable', 'integer', 'min:0'],
        ]);

        [$year, $month] = array_map('intval', explode('-', $data['month']));

        $brand->usageFor($year, $month)->update([
            'extra_posts' => $data['extra_posts'] ?? 0,
            'extra_regenerations' => $data['extra_regenerations'] ?? 0,
            'extra_image_refreshes' => $data['extra_image_refreshes'] ?? 0,
        ]);

        return back()->with('success', "Extensiones de {$brand->name} actualizadas.");
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function period(Request $request): array
    {
        $period = $request->query('month');
        if (is_string($period) && preg_match('/^\d{4}-\d{2}$/', $period)) {
            return array_map('intval', explode('-', $period));
        }

        $now = CarbonImmutable::now('America/Caracas');

        return [$now->year, $now->month];
    }
}
