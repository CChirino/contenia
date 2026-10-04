<?php

namespace App\Console\Commands;

use App\Models\LlmLog;
use Illuminate\Console\Command;

class LlmInspectCommand extends Command
{
    protected $signature = 'llm:inspect {id?}';

    protected $description = 'Muestra la forma de los argumentos devueltos en una entrada de llm_logs (depuración)';

    public function handle(): int
    {
        $log = $this->argument('id') ? LlmLog::findOrFail($this->argument('id')) : LlmLog::latest('id')->first();
        $r = json_decode($log->response, true);
        $args = $r['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? null;
        $this->line("log #{$log->id} model={$log->model} finish=".($r['choices'][0]['finish_reason'] ?? 'n/a'));
        $this->line('arguments type: '.gettype($args));
        $d = is_string($args) ? json_decode($args, true) : $args;
        $this->line('decoded type: '.gettype($d).' keys: '.implode(',', array_keys(is_array($d) ? $d : [])));
        $p = $d['posts'] ?? null;
        $this->line('posts type: '.gettype($p));
        $this->line(substr(is_string($p) ? $p : json_encode($p, JSON_UNESCAPED_UNICODE), 0, 700));

        if (is_string($p)) {
            json_decode($p, true);
            $this->line('decode error: '.json_last_error_msg().' | len='.strlen($p).' | tail: '.substr($p, -200));
        }

        return self::SUCCESS;
    }
}
