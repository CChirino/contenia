<?php

namespace Tests\Feature;

use App\Models\BrandUsage;
use App\Models\LlmLog;
use App\Services\Llm\LlmPricing;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LlmPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_precio_exacto_por_modelo(): void
    {
        // claude-sonnet-4-5: 3 / 15 USD por millón de tokens.
        $this->assertEqualsWithDelta(
            0.018,
            LlmPricing::cost('claude-sonnet-4-5', 1_000, 1_000),
            1e-9
        );
        $this->assertEqualsWithDelta(
            0.75,
            LlmPricing::cost('openai/gpt-4o-mini', 1_000_000, 1_000_000),
            1e-9
        );
    }

    public function test_sufijo_free_es_gratis(): void
    {
        $this->assertSame(0.0, LlmPricing::cost('openai/gpt-4o-mini:free', 500_000, 500_000));
        $this->assertSame(0.0, LlmPricing::cost('nvidia/nemotron-3-super-120b-a12b:free', 10, 10));
    }

    public function test_prefijo_con_fecha_o_sufijo(): void
    {
        $this->assertEqualsWithDelta(
            LlmPricing::cost('claude-sonnet-4-5', 1000, 1000),
            LlmPricing::cost('claude-sonnet-4-5-20250929', 1000, 1000),
            1e-9
        );
    }

    public function test_modelo_desconocido_cuesta_cero(): void
    {
        $this->assertSame(0.0, LlmPricing::cost('modelo-inventado', 9_999_999, 9_999_999));
        $this->assertSame(0.0, LlmPricing::cost('fake', 100, 100));
    }

    public function test_observer_suma_tokens_al_periodo_del_contenedor(): void
    {
        app()->instance('usage_period', [2026, 11]);

        LlmLog::create([
            'purpose' => 'test',
            'prompt' => 'p',
            'response' => 'r',
            'input_tokens' => 100,
            'output_tokens' => 200,
            'cost_usd' => 0.001,
            'model' => 'claude-sonnet-4-5',
            'duration_ms' => 5,
        ]);

        $usage = BrandUsage::where('year', 2026)->where('month', 11)->firstOrFail();
        $this->assertSame(100, (int) $usage->llm_tokens_in);
        $this->assertSame(200, (int) $usage->llm_tokens_out);

        // La fila de octubre del backfill existe pero quedó intacta.
        $this->assertSame(
            0,
            (int) BrandUsage::where('year', 2026)->where('month', 10)->value('llm_tokens_in')
        );
    }

    public function test_observer_usa_el_mes_del_log_sin_periodo(): void
    {
        app()->forgetInstance('usage_period');
        Carbon::setTestNow('2026-11-05 12:00:00');

        LlmLog::create([
            'purpose' => 'test',
            'prompt' => 'p',
            'response' => 'r',
            'input_tokens' => 50,
            'output_tokens' => 60,
            'cost_usd' => 0,
            'model' => 'fake',
            'duration_ms' => 1,
        ]);

        $usage = BrandUsage::where('year', 2026)->where('month', 11)->firstOrFail();
        $this->assertSame(50, (int) $usage->llm_tokens_in);
        $this->assertSame(60, (int) $usage->llm_tokens_out);
        // La fila de octubre del backfill no recibió nada.
        $this->assertSame(
            0,
            (int) BrandUsage::where('year', 2026)->where('month', 10)->value('llm_tokens_in')
        );
    }
}
