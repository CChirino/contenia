<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * Cuota del plan agotada: un ValidationException con clave `quota` que
 * Inertia muestra como 422 en el formulario/página.
 */
class QuotaExceededException extends ValidationException
{
    public static function for(string $label, int $limit, string $planName, string $month): static
    {
        return static::withMessages([
            'quota' => "Esta marca agotó los/las {$limit} {$label} del plan {$planName} para {$month}.",
        ]);
    }
}
