<?php

return [
    /*
     * Precio en USD por millón de tokens. Coincidencia exacta del nombre del
     * modelo; los sufijos ':free' (OpenRouter) se consideran 0. Todo lo no
     * listado usa 'default' (0 — sin coste computado).
     */
    'models' => [
        'anthropic/claude-sonnet-4.5' => ['input' => 3.0, 'output' => 15.0],
        'claude-sonnet-4-5' => ['input' => 3.0, 'output' => 15.0],
        'anthropic/claude-haiku-4.5' => ['input' => 1.0, 'output' => 5.0],
        'openai/gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'google/gemini-2.5-flash' => ['input' => 0.30, 'output' => 2.50],
    ],

    'default' => ['input' => 0.0, 'output' => 0.0],
];
