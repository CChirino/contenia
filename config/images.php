<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver de generación de fondos
    |--------------------------------------------------------------------------
    |
    | "pollinations" usa la API de Pollinations, "geometric" dibuja un fondo
    | de marca con GD (fallback que siempre funciona) y "fake" devuelve un
    | PNG mínimo para tests.
    |
    */

    'driver' => env('IMAGE_DRIVER', 'pollinations'),

    'fallback' => env('IMAGE_FALLBACK', 'geometric'),

    'pollinations' => [
        'api_key' => env('POLLINATIONS_API_KEY'),
        'model' => env('POLLINATIONS_MODEL', 'flux'),
    ],

    'style_suffix' => 'ilustración plana minimalista, paleta azul oscuro #1A2A42, teal #27C2A5, morado #7854D2, crema #F8F5F0, sin texto, sin letras, sin personas reales, alta calidad',

];
