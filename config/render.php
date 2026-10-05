<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Render de diseños (Playwright)
    |--------------------------------------------------------------------------
    |
    | "internal_url" es la base que usa Chromium dentro del contenedor para
    | resolver las rutas firmadas /render/{post}/{frame} y /assets/{post}/{file}.
    | El proceso Node ejecuta scripts/render.mjs con los flags --url-template,
    | --out-dir, --width, --height y --frames.
    |
    */

    'internal_url' => env('RENDER_INTERNAL_URL', 'http://localhost'),

    'node_binary' => env('RENDER_NODE_BINARY', 'node'),

    'script' => env('RENDER_SCRIPT', base_path('scripts/render.mjs')),

];
