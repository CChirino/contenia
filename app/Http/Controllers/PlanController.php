<?php

namespace App\Http\Controllers;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Jobs\GenerateMonthlyPlan;
use App\Models\ContentPillar;
use App\Models\MonthlyPlan;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function show(int $year, int $month): Response
    {
        $plan = MonthlyPlan::where('year', $year)->where('month', $month)->first();

        $posts = $plan
            ? $plan->posts()
                ->with('contentPillar')
                ->orderBy('scheduled_at')
                ->get()
                ->map(fn ($post) => $post->toArray() + [
                    'scheduled_date' => $post->scheduled_at->format('Y-m-d'),
                    'scheduled_time' => $post->scheduled_at->format('H:i'),
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
        ]);
    }

    public function generate(int $year, int $month): RedirectResponse
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

        GenerateMonthlyPlan::dispatch($year, $month);

        return back()->with('success', 'Generación de la grilla encolada.');
    }

    public function regenerateWeek(int $year, int $month, string $from): RedirectResponse
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

        GenerateMonthlyPlan::dispatch($year, $month, $date->format('Y-m-d'));

        return back()->with('success', 'Regeneración de la semana encolada.');
    }

    public function clearError(int $year, int $month): RedirectResponse
    {
        MonthlyPlan::where('year', $year)->where('month', $month)
            ->update(['last_error' => null]);

        return back();
    }

    public function status(int $year, int $month): JsonResponse
    {
        $plan = MonthlyPlan::where('year', $year)->where('month', $month)->first();

        return response()->json([
            'generated_at' => $plan?->generated_at?->toIso8601String(),
            'posts_count' => $plan?->posts()->count() ?? 0,
            'pending' => $plan !== null && $plan->generating_at !== null,
            'last_error' => $plan?->last_error,
        ]);
    }
}
