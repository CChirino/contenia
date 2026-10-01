<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver del cliente LLM
    |--------------------------------------------------------------------------
    |
    | "anthropic" llama a la API de Anthropic, "openrouter" usa OpenRouter
    | y "fake" devuelve respuestas encoladas para desarrollo y pruebas.
    |
    */

    'driver' => env('LLM_DRIVER', 'anthropic'),

    'timeout' => env('LLM_TIMEOUT', 300),

    'retries' => env('LLM_RETRIES', 3),

    'max_tokens' => env('LLM_MAX_TOKENS', 8000),

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL', 'anthropic/claude-sonnet-4.5'),
    ],

];
