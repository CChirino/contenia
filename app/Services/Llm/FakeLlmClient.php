<?php

namespace App\Services\Llm;

use App\Models\LlmLog;

class FakeLlmClient implements LlmClient
{
    /** @var array<int, array<string, mixed>> */
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

    public function complete(string $system, string $user, array $jsonSchema, string $purpose): array
    {
        $result = array_shift($this->responses) ?? [];

        LlmLog::create([
            'purpose' => $purpose,
            'prompt' => $system."\n\n".$user,
            'response' => json_encode($result),
            'input_tokens' => 0,
            'output_tokens' => 0,
            'model' => 'fake',
            'duration_ms' => 0,
        ]);

        return $result;
    }
}
