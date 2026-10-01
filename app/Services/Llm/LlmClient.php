<?php

namespace App\Services\Llm;

interface LlmClient
{
    /**
     * Ejecuta una completion con salida estructurada según $jsonSchema.
     * Registra la llamada en llm_logs.
     *
     * @return array<string, mixed> JSON decodificado de la respuesta
     *
     * @throws LlmException
     */
    public function complete(string $system, string $user, array $jsonSchema, string $purpose): array;
}
