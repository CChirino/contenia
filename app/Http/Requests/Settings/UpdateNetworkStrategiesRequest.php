<?php

namespace App\Http\Requests\Settings;

use App\Enums\PostFormat;
use App\Models\NetworkStrategy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateNetworkStrategiesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'strategies' => ['required', 'array'],
            'strategies.*.id' => ['required', 'integer', 'exists:network_strategies,id'],
            'strategies.*.posts_per_week' => ['required', 'integer', 'min:0', 'max:14'],
            'strategies.*.preferred_formats' => ['required', 'array', 'min:1'],
            'strategies.*.preferred_formats.*' => ['string', 'in:image,carousel,reel,video'],
            'strategies.*.focus' => ['required', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ((array) $this->input('strategies', []) as $index => $data) {
                    $strategy = isset($data['id']) ? NetworkStrategy::find($data['id']) : null;

                    if (! $strategy) {
                        continue;
                    }

                    $allowed = collect(PostFormat::allowedFor($strategy->network))->map->value;

                    foreach ((array) ($data['preferred_formats'] ?? []) as $format) {
                        if (! $allowed->contains($format)) {
                            $validator->errors()->add(
                                "strategies.{$index}.preferred_formats",
                                "El formato «{$format}» no está permitido en {$strategy->network->value}."
                            );
                        }
                    }
                }
            },
        ];
    }
}
