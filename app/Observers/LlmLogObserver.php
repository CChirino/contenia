<?php

namespace App\Observers;

use App\Models\Brand;
use App\Models\BrandUsage;
use App\Models\LlmLog;

/**
 * Cada llamada al LLM suma sus tokens al consumo del mes de contenido.
 * El periodo se fija con app()->instance('usage_period', [year, month])
 * desde los jobs; sin periodo se usa el mes de creación del log.
 */
class LlmLogObserver
{
    public function created(LlmLog $log): void
    {
        $brand = Brand::find($log->brand_id);
        if (! $brand) {
            return;
        }

        [$year, $month] = app()->bound('usage_period')
            ? app('usage_period')
            : [$log->created_at->year, $log->created_at->month];

        $usage = $brand->usageFor($year, $month);

        // Incremento atómico limitado a la fila (increment() sobre el modelo
        // delegaría al query builder sin la clave primaria).
        BrandUsage::withoutGlobalScopes()
            ->whereKey($usage->id)
            ->increment('llm_tokens_in', (int) $log->input_tokens, [
                'llm_tokens_out' => (int) $log->output_tokens,
                'updated_at' => now(),
            ]);
    }
}
