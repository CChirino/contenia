<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNetworkStrategiesRequest;
use App\Models\NetworkStrategy;
use App\Services\Quota\QuotaGuard;
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

        $brand = app('brand');
        $quota = app(QuotaGuard::class);

        return Inertia::render('Settings/Networks', [
            'strategies' => NetworkStrategy::orderBy('id')->get(),
            'allowedFormats' => $allowedFormats,
            'allowed_networks' => collect(Network::cases())
                ->filter(fn (Network $n) => $quota->allowsNetwork($brand, $n))
                ->map->value
                ->values(),
            'plan_name' => $brand->plan?->name ?? 'Sin plan',
        ]);
    }

    public function update(UpdateNetworkStrategiesRequest $request): RedirectResponse
    {
        $quota = app(QuotaGuard::class);

        foreach ($request->validated()['strategies'] as $data) {
            $strategy = NetworkStrategy::findOrFail($data['id']);

            // Una red fuera del plan de la marca no puede tener cadencia.
            $postsPerWeek = $quota->allowsNetwork(app('brand'), $strategy->network)
                ? $data['posts_per_week']
                : 0;

            $strategy->update([
                'posts_per_week' => $postsPerWeek,
                'preferred_formats' => $data['preferred_formats'],
                'focus' => $data['focus'],
            ]);
        }

        return redirect()->route('settings.networks.index');
    }
}
