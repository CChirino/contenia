<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inspector de archivos multimedia (posts desde archivo)
    |--------------------------------------------------------------------------
    |
    | "ffmpeg" usa ffprobe/ffmpeg + GD del contenedor; "fake" devuelve
    | metadatos y fotogramas fijos en tests.
    |
    */

    'inspector' => env('MEDIA_INSPECTOR', 'ffmpeg'),

    'image_max_mb' => 10,

    'video_max_mb' => 100,

    'video_max_seconds' => 90,

    'allowed_image_mimes' => ['image/jpeg', 'image/png', 'image/webp'],

    'allowed_video_mimes' => ['video/mp4', 'video/quicktime'],

    // Lado largo máximo de las imágenes enviadas al modelo de visión.
    'vision_image_max_side' => 1024,

    'vision_frame_max_side' => 768,

];
