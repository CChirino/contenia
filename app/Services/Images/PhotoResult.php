<?php

namespace App\Services\Images;

class PhotoResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $bytes,
        public readonly array $meta,
    ) {}
}
