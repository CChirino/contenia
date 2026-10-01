<?php

namespace App\Services\Llm;

use App\Models\LlmLog;
use Illuminate\Support\Facades\Http;

class OpenRouterClient implements LlmClient
{
    protected const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    protected const MAX_TOKENS = 16000;

    public function complete(string $system, string $user, array $jsonSchema, string $purpose): array
    {
        $model = config('llm.openrouter.model');
        $start = hrtime(true);

        $response = Http::timeout(120)
            ->withHeaders([
                'Authorization' => 'Bearer '.config('llm.openrouter.api_key'),
                'HTTP-Referer' => config('app.url'),
                'X-Title' => 'Sabere Marketing',
            ])
            ->post(self::ENDPOINT, [
                'model' => $model,
                'max_tokens' => self::MAX_TOKENS,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'tools' => [
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'emit_result',
                            'description' => 'Emite el resultado estructurado de la solicitud.',
                            'parameters' => $jsonSchema,
                        ],
                    ],
                ],
                'tool_choice' => ['type' => 'function', 'function' => ['name' => 'emit_result']],
            ]);

        $durationMs = (int) ((hrtime(true) - $start) / 1e6);

        if (! $response->successful()) {
            throw new LlmException(
                'Error HTTP '.$response->status().' de OpenRouter: '.$response->body()
            );
        }

        $arguments = $response->json('choices.0.message.tool_calls.0.function.arguments');

        if (! is_string($arguments)) {
            throw new LlmException(
                'La respuesta de OpenRouter no contiene tool_calls.'
            );
        }

        $decoded = json_decode($arguments, true);

        if (! is_array($decoded)) {
            throw new LlmException(
                'Los argumentos de la herramienta de OpenRouter no son JSON válido.'
            );
        }

        LlmLog::create([
            'purpose' => $purpose,
            'prompt' => $system."\n\n".$user,
            'response' => $response->body(),
            'input_tokens' => (int) $response->json('usage.prompt_tokens', 0),
            'output_tokens' => (int) $response->json('usage.completion_tokens', 0),
            'model' => $response->json('model') ?? $model,
            'duration_ms' => $durationMs,
        ]);

        return $decoded;
    }
}
