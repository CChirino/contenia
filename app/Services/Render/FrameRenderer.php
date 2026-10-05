<?php

namespace App\Services\Render;

use App\Models\Post;

interface FrameRenderer
{
    /**
     * Renderiza los frames del post y devuelve los nombres de archivo
     * generados dentro de storage/app/assets/{post_id}/ (p. ej. frame-01.png).
     *
     * @return array<int, string>
     *
     * @throws RenderException
     */
    public function render(Post $post): array;
}
