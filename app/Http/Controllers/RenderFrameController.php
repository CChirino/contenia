<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\BrandProfile;
use App\Models\Post;
use App\Services\Render\TemplateRegistry;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class RenderFrameController extends Controller
{
    public function __invoke(Brand $brand, string $post, int $frame): Response
    {
        // Sin scope de marca: un post de otra marca es 403, no 404.
        $post = Post::withoutGlobalScopes()->findOrFail((int) $post);
        abort_unless($post->brand_id === $brand->id, 403);

        // Ruta firmada: no pasa por SetActiveBrand; la marca se fija aquí.
        app()->instance('brand', $brand);

        Inertia::setRootView('render');

        $template = TemplateRegistry::for($post->network, $post->format);
        $framesTotal = TemplateRegistry::frameCount($post);

        abort_if($frame < 1 || $frame > $framesTotal, 404);

        $post->loadMissing('contentPillar');
        $profile = BrandProfile::current();

        return Inertia::render('Render/Frame', [
            'template' => [
                'name' => $template->name,
                'component' => $template->component,
                'width' => $template->width,
                'height' => $template->height,
                'photo_height' => $template->photoHeight,
            ],
            'frame' => $frame,
            'framesTotal' => $framesTotal,
            'post' => [
                'network' => $post->network->value,
                'hook' => $post->hook,
                'copy' => $post->copy,
                'design_payload' => $post->design_payload,
                'content_pillar' => ['name' => $post->contentPillar?->name],
            ],
            'backgroundUrl' => $this->backgroundUrl($post, $frame, $template->usesAiBackground),
            'icon' => $post->icon?->value ?? 'lightbulb',
            'logoUrl' => $brand->logoUrl(),
            'wordmark' => $brand->name.'.',
            'palette' => $brand->palette,
            'brand' => [
                'url' => $profile->url,
                'cta' => $profile->cta,
            ],
        ]);
    }

    private function backgroundUrl(Post $post, int $frame, bool $usesAi): ?string
    {
        if (! $usesAi || ! Storage::disk('local')->exists($post->assetPath('bg.png'))) {
            return null;
        }

        return URL::temporarySignedRoute('assets.show', now()->addMinutes(10), [
            'brand' => $post->brand->slug,
            'post' => $post->id,
            'file' => 'bg.png',
        ]);
    }
}
