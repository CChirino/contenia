<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandProfileRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:600'],
            'tone_of_voice' => ['required', 'string'],
            'audience' => ['required', 'string'],
            'base_hashtags' => ['required', 'array'],
            'base_hashtags.*' => ['string', 'max:100'],
            'cta' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:255'],
            'palette' => ['required', 'array'],
            'palette.*' => ['string', 'max:20'],
        ];
    }
}
