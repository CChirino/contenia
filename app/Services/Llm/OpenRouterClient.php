<?php

namespace App\Services\Llm;

use App\Models\LlmLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class OpenRouterClient implements LlmClient
{
    protected const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    public function complete(string $system, string $user, array $jsonSchema, string $purpose): array
    {
        $model = config('llm.openrouter.model');
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
                'Authorization' => 'Bearer '.config('llm.openrouter.api_key'),
                'HTTP-Referer' => config('app.url'),
                'X-Title' => 'Contenia',
            ])
            ->post(self::ENDPOINT, [
                'model' => $model,
                'max_tokens' => (int) config('llm.max_tokens', 8000),
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
            $detail = $response->json('error.metadata.raw')
                ?? $response->json('error.message')
                ?? substr($response->body(), 0, 200);

            throw new LlmException(
                'Error HTTP '.$response->status().' de OpenRouter: '.$detail
            );
        }

        $arguments = $response->json('choices.0.message.tool_calls.0.function.arguments');

        // Algunos modelos ignoran tool_choice y devuelven el JSON como texto plano
        // (a veces envuelto en ```json ... ```); lo aceptamos como fallback.
        if (! is_string($arguments)) {
            $content = $response->json('choices.0.message.content');

            if (! is_string($content) || ! preg_match('/\{.*\}/s', $content, $m)) {
                throw new LlmException(
                    'La respuesta de OpenRouter no contiene tool_calls ni JSON en el contenido'
                    .' (finish_reason: '.($response->json('choices.0.finish_reason') ?? 'n/a').').'
                );
            }

            $arguments = $m[0];
        }

        $decoded = json_decode($arguments, true);

        if (! is_array($decoded)) {
            throw new LlmException(
                'Los argumentos de la herramienta de OpenRouter no son JSON válido.'
            );
        }

        $usedModel = $response->json('model') ?? $model;
        $in = (int) $response->json('usage.prompt_tokens', 0);
        $out = (int) $response->json('usage.completion_tokens', 0);

        LlmLog::create([
            'purpose' => $purpose,
            'prompt' => $system."\n\n".$user,
            'response' => $response->body(),
            'input_tokens' => $in,
            'output_tokens' => $out,
            'cost_usd' => LlmPricing::cost($usedModel, $in, $out),
            'model' => $usedModel,
            'duration_ms' => $durationMs,
        ]);

        return $decoded;
    }

    /**
     * Modelo de visión vía OpenRouter: sin tools — el modelo probado
     * (nemotron-3.5-super-vl:free) responde bien con response_format
     * json_object; se tolera ```json y truncamiento como en complete().
     */
    public function describeImages(string $system, string $user, array $imageDataUrls, array $jsonSchema, string $purpose): array
    {
        $model = config('llm.openrouter.vision_model');
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
                'Authorization' => 'Bearer '.config('llm.openrouter.api_key'),
                'HTTP-Referer' => config('app.url'),
                'X-Title' => 'Contenia',
            ])
            ->post(self::ENDPOINT, [
                'model' => $model,
                'max_tokens' => 2000,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    [
                        'role' => 'user',
                        'content' => array_merge(
                            [['type' => 'text', 'text' => $user]],
                            array_map(
                                fn (string $url) => [
                                    'type' => 'image_url',
                                    'image_url' => ['url' => $url],
                                ],
                                $imageDataUrls
                            ),
                        ),
                    ],
                ],
            ]);

        $durationMs = (int) ((hrtime(true) - $start) / 1e6);

        if (! $response->successful()) {
            $detail = $response->json('error.metadata.raw')
                ?? $response->json('error.message')
                ?? substr($response->body(), 0, 200);

            throw new LlmException(
                'Error HTTP '.$response->status().' de OpenRouter (visión): '.$detail
            );
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content)
            || ! preg_match('/\{.*\}/s', preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($content)), $m)) {
            throw new LlmException(
                'La respuesta de visión de OpenRouter no contiene JSON'
                .' (finish_reason: '.($response->json('choices.0.finish_reason') ?? 'n/a').').'
            );
        }

        $decoded = json_decode($m[0], true);

        if (! is_array($decoded)) {
            throw new LlmException('La respuesta de visión de OpenRouter no es JSON válido.');
        }

        $usedModel = $response->json('model') ?? $model;
        $in = (int) $response->json('usage.prompt_tokens', 0);
        $out = (int) $response->json('usage.completion_tokens', 0);

        LlmLog::create([
            'purpose' => $purpose,
            'prompt' => $system."\n\n".$user,
            'response' => $response->body(),
            'input_tokens' => $in,
            'output_tokens' => $out,
            'cost_usd' => LlmPricing::cost($usedModel, $in, $out),
            'model' => $usedModel,
            'duration_ms' => $durationMs,
        ]);

        return $decoded;
    }
}
