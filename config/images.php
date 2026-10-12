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
        'model' => env('POLLINATIONS_MODEL', 'microsoft/mai-image-2.6-flash'),
        'base_url' => env('POLLINATIONS_BASE_URL', 'https://gen.pollinations.ai'),
    ],

    /*
    | Fotos de stock (Pixabay) para los fondos "foto real + marca". Se usa
    | antes que el generador IA cuando el post tiene photo_keywords.
    */

    'photo_source' => env('PHOTO_SOURCE', 'pixabay'),

    'pixabay' => [
        'api_key' => env('PIXABAY_API_KEY'),
        'exclude_tags' => [
            'child', 'children', 'kid', 'kids', 'boy', 'boys', 'girl', 'girls',
            'baby', 'toddler', 'infant', 'teen', 'teenager', 'pupil', 'pupils',
        ],
    ],

    'style_suffix' => 'natural soft daylight, shot on 35mm lens, shallow depth of field, muted natural colors, slightly desaturated, realistic, no HDR, no oversaturation, no people, no faces, no text, no letters, no signs, no watermark',

];
