<?php

namespace App\Http\Requests\Settings;

use App\Models\ContentPillar;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreContentPillarRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'weight' => [
                'required',
                'integer',
                'min:0',
                'max:100',
                function (string $attribute, mixed $value, Closure $fail) {
                    $total = (int) ContentPillar::sum('weight') + (int) $value;

                    if ($total !== 100) {
                        $fail('La suma de los pesos de todos los pilares debe ser 100 (actualmente sería '.$total.').');
                    }
                },
            ],
        ];
    }
}
