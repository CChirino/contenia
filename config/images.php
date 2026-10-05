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
        'model' => env('POLLINATIONS_MODEL', 'z-image-turbo'),
        'base_url' => env('POLLINATIONS_BASE_URL', 'https://gen.pollinations.ai'),
    ],

    'style_suffix' => 'flat minimalist vector illustration, dark navy #1A2A42 background, teal #27C2A5 and purple #7854D2 accents, cream #F8F5F0 highlights, soft shapes, no text, no letters, no words, no watermark, no people, high quality',

];
