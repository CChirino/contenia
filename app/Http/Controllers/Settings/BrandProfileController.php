<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateBrandProfileRequest;
use App\Models\BrandProfile;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BrandProfileController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Settings/Brand', [
            'brandProfile' => BrandProfile::current(),
        ]);
    }

    public function update(UpdateBrandProfileRequest $request): RedirectResponse
    {
        BrandProfile::current()->update($request->validated());

        return redirect()->route('settings.brand.edit');
    }
}
