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

    /**
     * Describe imágenes con un modelo de visión: devuelve el JSON
     * decodificado según $jsonSchema. $imageDataUrls son data URLs
     * (data:image/jpeg;base64,...). Registra la llamada en llm_logs.
     *
     * @param  array<int, string>  $imageDataUrls
     * @return array<string, mixed>
     *
     * @throws LlmException
     */
    public function describeImages(string $system, string $user, array $imageDataUrls, array $jsonSchema, string $purpose): array;
}
