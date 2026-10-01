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

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL', 'anthropic/claude-sonnet-4.5'),
    ],

];
