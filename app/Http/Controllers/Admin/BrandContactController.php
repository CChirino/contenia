<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BrandContactController extends Controller
{
    public function store(Request $request, Brand $brand): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'email', 'max:190',
                Rule::unique('brand_contacts')->where('brand_id', $brand->id),
            ],
            'whatsapp' => ['nullable', 'string', 'max:40'],
        ]);

        $brand->contacts()->create($validated);

        return back()->with('success', 'Contacto añadido.');
    }

    public function destroy(Brand $brand, BrandContact $contact): RedirectResponse
    {
        abort_if($contact->brand_id !== $brand->id, 404);

        $contact->delete();

        return back()->with('success', 'Contacto eliminado.');
    }
}
