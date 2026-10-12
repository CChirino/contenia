<?php

namespace App\Services\Render;

use App\Enums\Network;
use App\Enums\PostFormat;

class Template
{
    public function __construct(
        public string $name,
        public Network $network,
        public PostFormat $format,
        public int $width,
        public int $height,
        public int $photoHeight,
        public string $component,
        public bool $usesAiBackground,
    ) {}
}
