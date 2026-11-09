<?php

namespace App\Services\Llm;

use App\Models\LlmLog;

class FakeLlmClient implements LlmClient
{
    /** @var array<int, array<string, mixed>|LlmException> */
    protected array $responses = [];

    /**
     * Encola una respuesta para la próxima llamada a complete().
     *
     * @param  array<string, mixed>  $response
     */
    public function withResponse(array $response): static
    {
        $this->responses[] = $response;

        return $this;
    }

    /**
     * Encola un fallo LlmException para la próxima llamada a complete().
     */
    public function withException(string $message = 'Error simulado del LLM.'): static
    {
        $this->responses[] = new LlmException($message);

        return $this;
    }

    public function complete(string $system, string $user, array $jsonSchema, string $purpose): array
    {
        $result = array_shift($this->responses) ?? [];

        if ($result instanceof LlmException) {
            throw $result;
        }

        LlmLog::create([
            'purpose' => $purpose,
            'prompt' => $system."\n\n".$user,
            'response' => json_encode($result),
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost_usd' => 0,
            'model' => 'fake',
            'duration_ms' => 0,
        ]);

        return $result;
    }

    /** @var array<int, array{system: string, user: string, images: int, purpose: string}> */
    public array $describeCalls = [];

    /**
     * Comparte la cola de withResponse()/withException() con complete():
     * las respuestas se consumen en orden de llamada. Las llamadas se
     * registran en describeCalls para que los tests aserten.
     */
    public function describeImages(string $system, string $user, array $imageDataUrls, array $jsonSchema, string $purpose): array
    {
        $this->describeCalls[] = [
            'system' => $system,
            'user' => $user,
            'images' => count($imageDataUrls),
            'purpose' => $purpose,
        ];

        $result = array_shift($this->responses) ?? [];

        if ($result instanceof LlmException) {
            throw $result;
        }

        LlmLog::create([
            'purpose' => $purpose,
            'prompt' => $system."\n\n".$user,
            'response' => json_encode($result),
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost_usd' => 0,
            'model' => 'fake',
            'duration_ms' => 0,
        ]);

        return $result;
    }
}
