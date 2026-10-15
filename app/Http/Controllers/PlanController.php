<?php

namespace App\Http\Controllers;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Jobs\GenerateMonthlyPlan;
use App\Models\Brand;
use App\Models\ContentPillar;
use App\Models\MonthlyPlan;
use App\Models\Post;
use App\Models\ReviewItem;
use App\Models\ReviewRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function show(Brand $brand, int $year, int $month): Response
    {
        $plan = MonthlyPlan::where('year', $year)->where('month', $month)->first();

        // Decisión más reciente de cada post en revisiones del cliente.
        $latestItems = ReviewItem::query()
            ->whereHas('reviewRequest', fn ($q) => $q->where('brand_id', $brand->id))
            ->orderByDesc('id')
            ->get()
            ->keyBy('post_id');

        $posts = $plan
            ? $plan->posts()
                ->with('contentPillar')
                ->orderBy('scheduled_at')
                ->get()
                ->map(fn ($post) => $post->toArray() + [
                    'scheduled_date' => $post->scheduled_at->format('Y-m-d'),
                    'scheduled_time' => $post->scheduled_at->format('H:i'),
                    'asset_urls' => $this->assetUrls($post),
                    'review' => $latestItems->has($post->id)
                        ? [
                            'decision' => $latestItems[$post->id]->decision->value,
                            'comment' => $latestItems[$post->id]->comment,
                        ]
                        : null,
                ])
            : collect();

        $allowedFormats = collect(Network::cases())
            ->mapWithKeys(fn (Network $network) => [
                $network->value => collect(PostFormat::allowedFor($network))->map->value,
            ]);

        $weeks = collect(GenerateMonthlyPlan::weeksOf($year, $month))
            ->map(fn (array $range) => [
                'from' => $range[0]->format('Y-m-d'),
                'to' => $range[1]->format('Y-m-d'),
                'posts_count' => $plan
                    ? $plan->posts()->whereBetween('scheduled_at', [$range[0], $range[1]->endOfDay()])->count()
                    : 0,
            ]);

        // Solicitudes de revisión del cliente del mes: abiertas o enviadas
        // en los últimos 60 días.
        $monthStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->endOfMonth();

        $reviews = $brand->reviewRequests()
            ->with('weekComments')
            ->where('from_date', '<=', $monthEnd->toDateString())
            ->where('to_date', '>=', $monthStart->toDateString())
            ->where(fn ($q) => $q->open()->orWhere('created_at', '>=', now()->subDays(60)))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ReviewRequest $review) => [
                'id' => $review->id,
                'from_date' => $review->from_date->format('Y-m-d'),
                'to_date' => $review->to_date->format('Y-m-d'),
                'status' => $review->state(),
                'created_at' => $review->created_at->toIso8601String(),
                'expires_at' => $review->expires_at->format('Y-m-d'),
                'submitted_at' => $review->submitted_at?->toIso8601String(),
                'general_comment' => $review->general_comment,
                'summary' => $review->summary(),
                'week_comments' => $review->weekComments->map(fn ($c) => [
                    'week_from' => $c->week_from->format('Y-m-d'),
                    'comment' => $c->comment,
                ])->values(),
            ]);

        return Inertia::render('Plans/Show', [
            'year' => $year,
            'month' => $month,
            'plan' => $plan ? [
                'id' => $plan->id,
                'year' => $plan->year,
                'month' => $plan->month,
                'status' => $plan->status,
                'generated_at' => $plan->generated_at?->toIso8601String(),
                'generating_at' => $plan->generating_at?->toIso8601String(),
                'last_error' => $plan->last_error,
                'posts_count' => $posts->count(),
            ] : null,
            'posts' => $posts,
            'pillars' => ContentPillar::orderBy('id')->get(),
            'allowedFormats' => $allowedFormats,
            'weeks' => $weeks,
            'reviews' => $reviews,
        ]);
    }

    public function generate(Brand $brand, int $year, int $month): RedirectResponse
    {
        $pillars = ContentPillar::all();

        if ($pillars->isEmpty() || (int) $pillars->sum('weight') !== 100) {
            throw ValidationException::withMessages([
                'generate' => 'La suma de los pesos de los pilares debe ser 100 para poder generar la grilla.',
            ]);
        }

        $plan = MonthlyPlan::firstOrCreate(
            ['year' => $year, 'month' => $month],
            ['status' => 'draft']
        );

        abort_if($plan->generating_at !== null, 409, 'Ya hay una generación en curso para este mes.');

        $plan->update(['generating_at' => now(), 'last_error' => null]);

        GenerateMonthlyPlan::dispatch(app('brand'), $year, $month);

        return back()->with('success', 'Generación de la grilla encolada.');
    }

    public function regenerateWeek(Brand $brand, int $year, int $month, string $from): RedirectResponse
    {
        $date = CarbonImmutable::parse($from, 'America/Caracas');

        if ($date->year !== $year || $date->month !== $month) {
            throw ValidationException::withMessages([
                'week' => 'La fecha indicada no pertenece al mes.',
            ]);
        }

        $plan = MonthlyPlan::firstOrCreate(
            ['year' => $year, 'month' => $month],
            ['status' => 'draft']
        );

        abort_if($plan->generating_at !== null, 409, 'Ya hay una generación en curso para este mes.');

        $plan->update(['generating_at' => now(), 'last_error' => null]);

        GenerateMonthlyPlan::dispatch(app('brand'), $year, $month, $date->format('Y-m-d'));

        return back()->with('success', 'Regeneración de la semana encolada.');
    }

    public function clearError(Brand $brand, int $year, int $month): RedirectResponse
    {
        MonthlyPlan::where('year', $year)->where('month', $month)
            ->update(['last_error' => null]);

        return back();
    }

    public function status(Brand $brand, int $year, int $month): JsonResponse
    {
        $plan = MonthlyPlan::where('year', $year)->where('month', $month)->first();

        $renderingIds = $plan
            ? $plan->posts()->whereNotNull('render_requested_at')->pluck('id')
            : collect();

        return response()->json([
            'generated_at' => $plan?->generated_at?->toIso8601String(),
            'posts_count' => $plan?->posts()->count() ?? 0,
            'pending' => $plan !== null && $plan->generating_at !== null,
            'rendering_ids' => $renderingIds,
            'last_error' => $plan?->last_error,
        ]);
    }

    /**
     * URLs firmadas (60 min) para previsualizar los frames del post.
     *
     * @return array{frames: array<int, string>, final: string|null}
     */
    private function assetUrls(Post $post): array
    {
        $sign = fn (string $file) => URL::temporarySignedRoute(
            'assets.show',
            now()->addMinutes(60),
            ['brand' => $post->brand->slug, 'post' => $post->id, 'file' => $file],
        );

        $frames = collect($post->frames ?? [])
            ->filter(fn ($file) => Storage::disk('local')->exists($post->assetPath($file)))
            ->map($sign)
            ->values()
            ->all();

        $final = $post->asset_path && Storage::disk('local')->exists($post->asset_path)
            ? $sign(basename($post->asset_path))
            : null;

        return ['frames' => $frames, 'final' => $final];
    }
}
