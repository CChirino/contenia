<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBrandRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'palette' => ['required', 'array'],
            'palette.dark' => ['required', 'string', 'max:20'],
            'palette.light' => ['required', 'string', 'max:20'],
            'palette.accent' => ['required', 'string', 'max:20'],
            'palette.secondary' => ['required', 'string', 'max:20'],
            'requires_client_approval' => ['boolean'],
            'logo' => ['nullable', 'file', 'mimes:svg,png', 'max:1024'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->palette)) {
            $this->merge(['palette' => json_decode($this->palette, true)]);
        }

        $this->merge(['requires_client_approval' => $this->boolean('requires_client_approval')]);
    }
}
