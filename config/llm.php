<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver del cliente LLM
    |--------------------------------------------------------------------------
    |
    | "anthropic" llama a la API real de Anthropic; "fake" devuelve
    | respuestas encoladas para desarrollo y pruebas.
    |
    */

    'driver' => env('LLM_DRIVER', 'anthropic'),

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
    ],

];
