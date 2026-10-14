<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreContentPillarRequest;
use App\Http\Requests\Settings\UpdateContentPillarRequest;
use App\Models\Brand;
use App\Models\ContentPillar;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContentPillarController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Pillars', [
            'pillars' => ContentPillar::orderBy('id')->get(),
        ]);
    }

    public function store(StoreContentPillarRequest $request): RedirectResponse
    {
        ContentPillar::create($request->validated());

        return redirect()->route('settings.pillars.index');
    }

    public function update(UpdateContentPillarRequest $request, Brand $brand, ContentPillar $pillar): RedirectResponse
    {
        $pillar->update($request->validated());

        return redirect()->route('settings.pillars.index');
    }

    public function destroy(Brand $brand, ContentPillar $pillar): RedirectResponse
    {
        $pillar->delete();

        return redirect()->route('settings.pillars.index');
    }
}
