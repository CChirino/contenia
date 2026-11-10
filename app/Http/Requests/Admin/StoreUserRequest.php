<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // El índice único incluye eliminados: si el correo es de un
            // usuario eliminado, se pide restaurarlo en vez de duplicarlo.
            'email' => ['required', 'email', 'max:255', function (string $attribute, mixed $value, \Closure $fail) {
                $existing = User::withTrashed()->where('email', $value)->first();
                if ($existing?->trashed()) {
                    $fail('Ya existe un usuario eliminado con ese correo: restáuralo desde "Usuarios eliminados".');
                } elseif ($existing) {
                    $fail('Ya existe un usuario con ese correo.');
                }
            }],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'brand_ids' => ['array'],
            'brand_ids.*' => ['integer', 'exists:brands,id'],
        ];
    }
}
