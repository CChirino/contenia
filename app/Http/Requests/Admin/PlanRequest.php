<?php

namespace App\Http\Requests\Admin;

use App\Enums\Network;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            // Vacío = ilimitado (null).
            'posts_per_month' => ['nullable', 'integer', 'min:0'],
            'regenerations_per_month' => ['nullable', 'integer', 'min:0'],
            'image_refreshes_per_month' => ['nullable', 'integer', 'min:0'],
            'networks' => ['required', 'array', 'min:1'],
            'networks.*' => [Rule::enum(Network::class)],
            'price_label' => ['nullable', 'string', 'max:80'],
        ];
    }
}
