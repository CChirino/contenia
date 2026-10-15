<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Network;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Plans/Index', [
            'plans' => Plan::withCount('brands')->orderBy('id')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Plans/Form', [
            'plan' => null,
            'networks' => collect(Network::cases())->map->value,
        ]);
    }

    public function store(PlanRequest $request): RedirectResponse
    {
        Plan::create($request->validated());

        return redirect()->route('admin.plans.index');
    }

    public function edit(Plan $plan): Response
    {
        return Inertia::render('Admin/Plans/Form', [
            'plan' => $plan,
            'networks' => collect(Network::cases())->map->value,
        ]);
    }

    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update($request->validated());

        return redirect()->route('admin.plans.index');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->brands()->exists()) {
            throw ValidationException::withMessages([
                'plan' => 'No se puede eliminar un plan con marcas asignadas.',
            ]);
        }

        $plan->delete();

        return redirect()->route('admin.plans.index');
    }
}
