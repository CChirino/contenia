<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNetworkStrategiesRequest;
use App\Models\NetworkStrategy;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NetworkStrategyController extends Controller
{
    public function index(): Response
    {
        $allowedFormats = collect(Network::cases())
            ->mapWithKeys(fn (Network $network) => [
                $network->value => collect(PostFormat::allowedFor($network))->map->value,
            ]);

        return Inertia::render('Settings/Networks', [
            'strategies' => NetworkStrategy::orderBy('id')->get(),
            'allowedFormats' => $allowedFormats,
        ]);
    }

    public function update(UpdateNetworkStrategiesRequest $request): RedirectResponse
    {
        foreach ($request->validated()['strategies'] as $data) {
            NetworkStrategy::whereKey($data['id'])->update([
                'posts_per_week' => $data['posts_per_week'],
                'preferred_formats' => $data['preferred_formats'],
                'focus' => $data['focus'],
            ]);
        }

        return redirect()->route('settings.networks.index');
    }
}
