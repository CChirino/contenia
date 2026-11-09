<?php

namespace App\Http\Requests;

use App\Enums\MediaMode;
use App\Enums\Network;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostFromMediaRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxKb = (int) config('media.video_max_mb', 100) * 1024;

        return [
            'file' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime',
                "max:{$maxKb}",
            ],
            'network' => ['required', Rule::enum(Network::class)],
            'mode' => ['required', Rule::enum(MediaMode::class)],
            'date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Sube una foto o un video.',
            'file.mimetypes' => 'El archivo debe ser una imagen (JPG, PNG, WebP) o un video (MP4, MOV).',
            'file.max' => 'El archivo supera el tamaño máximo permitido.',
            'date.date_format' => 'La fecha debe tener formato AAAA-MM-DD.',
        ];
    }
}
