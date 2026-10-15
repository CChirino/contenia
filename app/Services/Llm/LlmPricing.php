<?php

namespace App\Services\Llm;

/**
 * Coste estimado de una llamada LLM en USD (precios por millón de tokens
 * en config/llm_pricing.php).
 */
class LlmPricing
{
    public static function cost(string $model, int $inputTokens, int $outputTokens): float
    {
        $prices = config("llm_pricing.models.{$model}")
            ?? static::forModelIgnoringSuffix($model)
            ?? config('llm_pricing.default', ['input' => 0.0, 'output' => 0.0]);

        return round(
            ($inputTokens * $prices['input'] + $outputTokens * $prices['output']) / 1_000_000,
            6
        );
    }

    /**
     * Prefijos habituales de OpenRouter: ':free' (gratis → 0 reales), ':nitro'
     * y variantes con fecha ('claude-sonnet-4-5-20250929' → 'claude-sonnet-4-5').
     *
     * @return array{input: float, output: float}|null
     */
    private static function forModelIgnoringSuffix(string $model): ?array
    {
        if (str_ends_with($model, ':free')) {
            return ['input' => 0.0, 'output' => 0.0];
        }

        $base = preg_replace('/[:@].*$/', '', $model);
        $models = config('llm_pricing.models', []);

        if (isset($models[$base])) {
            return $models[$base];
        }

        // Coincidencia por prefijo (versiones con fecha o sufijos numéricos).
        $best = null;
        foreach ($models as $name => $prices) {
            if ($base !== '' && str_starts_with($model, $name.'-')) {
                if ($best === null || strlen($name) > strlen($best[0])) {
                    $best = [$name, $prices];
                }
            }
        }

        return $best[1] ?? null;
    }
}
