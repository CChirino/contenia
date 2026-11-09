<?php

namespace App\Services\Llm;

use App\Models\LlmLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class AnthropicClient implements LlmClient
{
    protected const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public function complete(string $system, string $user, array $jsonSchema, string $purpose): array
    {
        $model = config('llm.anthropic.model');
        $start = hrtime(true);

        $response = Http::timeout((int) config('llm.timeout', 300))
            ->retry(
                (int) config('llm.retries', 3),
                fn (int $attempt) => $attempt * 5000,
                fn ($exception, $request) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->status() === 429 || $exception->response->serverError())),
                throw: false
            )
            ->withHeaders([
                'x-api-key' => config('llm.anthropic.api_key'),
                'anthropic-version' => '2023-06-01',
            ])
            ->post(self::ENDPOINT, [
                'model' => $model,
                'max_tokens' => (int) config('llm.max_tokens', 8000),
                'system' => $system,
                'messages' => [
                    ['role' => 'user', 'content' => $user],
                ],
                'tools' => [
                    [
                        'name' => 'emit_result',
                        'description' => 'Emite el resultado estructurado de la solicitud.',
                        'input_schema' => $jsonSchema,
                    ],
                ],
                'tool_choice' => ['type' => 'tool', 'name' => 'emit_result'],
            ]);

        $durationMs = (int) ((hrtime(true) - $start) / 1e6);

        if (! $response->successful()) {
            throw new LlmException(
                'Error HTTP '.$response->status().' de Anthropic: '.$response->body()
            );
        }

        $toolUse = collect($response->json('content', []))
            ->firstWhere('type', 'tool_use');

        if (! is_array($toolUse) || ! isset($toolUse['input']) || ! is_array($toolUse['input'])) {
            throw new LlmException(
                'La respuesta de Anthropic no contiene un bloque tool_use válido.'
            );
        }

        $in = (int) $response->json('usage.input_tokens', 0);
        $out = (int) $response->json('usage.output_tokens', 0);

        LlmLog::create([
            'purpose' => $purpose,
            'prompt' => $system."\n\n".$user,
            'response' => $response->body(),
            'input_tokens' => $in,
            'output_tokens' => $out,
            'cost_usd' => LlmPricing::cost($model, $in, $out),
            'model' => $model,
            'duration_ms' => $durationMs,
        ]);

        return $toolUse['input'];
    }

    /**
     * Visión con Anthropic: las data URLs se convierten en bloques
     * `image` base64; el resultado estructurado sale por la misma
     * herramienta emit_result que complete().
     */
    public function describeImages(string $system, string $user, array $imageDataUrls, array $jsonSchema, string $purpose): array
    {
        $model = config('llm.anthropic.model');
        $start = hrtime(true);

        $blocks = array_map(function (string $dataUrl) {
            abort_unless(
                preg_match('/^data:([^;]+);base64,(.+)$/s', $dataUrl, $m),
                422,
                'Data URL de imagen no válida.'
            );

            return [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $m[1],
                    'data' => $m[2],
                ],
            ];
        }, $imageDataUrls);

        $blocks[] = ['type' => 'text', 'text' => $user];

        $response = Http::timeout((int) config('llm.timeout', 300))
            ->retry(
                (int) config('llm.retries', 3),
                fn (int $attempt) => $attempt * 5000,
                fn ($exception, $request) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->status() === 429 || $exception->response->serverError())),
                throw: false
            )
            ->withHeaders([
                'x-api-key' => config('llm.anthropic.api_key'),
                'anthropic-version' => '2023-06-01',
            ])
            ->post(self::ENDPOINT, [
                'model' => $model,
                'max_tokens' => 2000,
                'system' => $system,
                'messages' => [
                    ['role' => 'user', 'content' => $blocks],
                ],
                'tools' => [
                    [
                        'name' => 'emit_result',
                        'description' => 'Emite el resultado estructurado de la solicitud.',
                        'input_schema' => $jsonSchema,
                    ],
                ],
                'tool_choice' => ['type' => 'tool', 'name' => 'emit_result'],
            ]);

        $durationMs = (int) ((hrtime(true) - $start) / 1e6);

        if (! $response->successful()) {
            throw new LlmException(
                'Error HTTP '.$response->status().' de Anthropic (visión): '.$response->body()
            );
        }

        $toolUse = collect($response->json('content', []))
            ->firstWhere('type', 'tool_use');

        if (! is_array($toolUse) || ! isset($toolUse['input']) || ! is_array($toolUse['input'])) {
            throw new LlmException(
                'La respuesta de visión de Anthropic no contiene un bloque tool_use válido.'
            );
        }

        $in = (int) $response->json('usage.input_tokens', 0);
        $out = (int) $response->json('usage.output_tokens', 0);

        LlmLog::create([
            'purpose' => $purpose,
            'prompt' => $system."\n\n".$user,
            'response' => $response->body(),
            'input_tokens' => $in,
            'output_tokens' => $out,
            'cost_usd' => LlmPricing::cost($model, $in, $out),
            'model' => $model,
            'duration_ms' => $durationMs,
        ]);

        return $toolUse['input'];
    }
}
