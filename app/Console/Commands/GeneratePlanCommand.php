<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMonthlyPlan;
use App\Models\LlmLog;
use App\Models\MonthlyPlan;
use App\Models\Post;
use Illuminate\Console\Command;
use Throwable;

class GeneratePlanCommand extends Command
{
    protected $signature = 'plan:generate {year} {month} {--queue : Encolar el job en vez de ejecutarlo en el momento}';

    protected $description = 'Genera la grilla mensual de contenido con el LLM configurado';

    public function handle(): int
    {
        $year = (int) $this->argument('year');
        $month = (int) $this->argument('month');

        if ($this->option('queue')) {
            GenerateMonthlyPlan::dispatch($year, $month);
            $this->info("Job encolado para {$year}-{$month}. Corre `queue:work` para procesarlo.");

            return self::SUCCESS;
        }

        $this->info("Generando grilla de {$year}-{$month} con driver ".config('llm.driver').'...');
        $logsBefore = LlmLog::max('id') ?? 0;

        try {
            dispatch_sync(new GenerateMonthlyPlan($year, $month));
        } catch (Throwable $e) {
            $this->error('Falló la generación: '.$e->getMessage());
        }

        $plan = MonthlyPlan::where('year', $year)->where('month', $month)->first();

        if ($plan?->last_error) {
            $this->warn($plan->last_error);
        }

        $this->table(
            ['Red', 'Posts'],
            $plan?->posts()->selectRaw('network, count(*) as c')->groupBy('network')->pluck('c', 'network')
                ->map(fn ($c, $n) => [$n, $c])->values()->all() ?? []
        );

        $this->table(
            ['Modelo', 'In', 'Out', 'ms'],
            LlmLog::where('id', '>', $logsBefore)->get()
                ->map(fn (LlmLog $l) => [$l->model, $l->input_tokens, $l->output_tokens, $l->duration_ms])->all()
        );

        $plan?->posts()->with('contentPillar')->inRandomOrder()->take(3)->get()->each(function (Post $p) {
            $this->line("\n--- {$p->network->value} {$p->scheduled_at->format('Y-m-d H:i')} [{$p->format->value}] ".($p->contentPillar->name ?? ''));
            $this->line("HOOK: {$p->hook}");
            $this->line("COPY: {$p->copy}");
            $this->line('TAGS: '.implode(' ', $p->hashtags));
        });

        return self::SUCCESS;
    }
}
