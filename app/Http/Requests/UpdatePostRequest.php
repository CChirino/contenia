<?php

namespace App\Http\Requests;

use App\Enums\PostFormat;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $post = $this->route('post');

        $allowedFormats = collect(PostFormat::allowedFor($post->network))->map->value;

        return [
            'copy' => ['required', 'string'],
            'hashtags' => ['required', 'array'],
            'hashtags.*' => ['string', 'max:100'],
            'scheduled_at' => ['required', 'date'],
            'format' => ['required', 'string', Rule::in($allowedFormats)],
        ];
    }
}
