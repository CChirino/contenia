<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\Llm\LlmClient;
use App\Services\Planning\PlanSchema;
use App\Services\Planning\PromptBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class LlmProbeCommand extends Command
{
    protected $signature = 'llm:probe {from} {to} {--brand=sabere : Slug de la marca}';

    protected $description = 'Ejecuta la llamada LLM de una semana y muestra la respuesta cruda (depuración)';

    public function handle(LlmClient $llm, PromptBuilder $prompts): int
    {
        $brand = Brand::where('slug', $this->option('brand'))->first();

        if (! $brand) {
            $this->error("Marca no encontrada: {$this->option('brand')}");

            return self::FAILURE;
        }

        app()->instance('brand', $brand);

        $from = CarbonImmutable::parse($this->argument('from'), 'America/Caracas');
        $to = CarbonImmutable::parse($this->argument('to'), 'America/Caracas');

        ['system' => $system, 'user' => $user] = $prompts->forWeek($from->year, $from->month, $from, $to, []);

        Http::globalResponseMiddleware(function ($response) {
            $body = (string) $response->getBody();
            $this->line('--- RAW ('.strlen($body)." bytes) ---\n".substr($body, 0, 1500)."\n...\n".substr($body, -600));

            return $response;
        });

        try {
            $result = $llm->complete($system, $user, PlanSchema::monthly(), 'probe');
            $this->info('OK: '.count($result['posts'] ?? []).' posts');
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }

        return self::SUCCESS;
    }
}
