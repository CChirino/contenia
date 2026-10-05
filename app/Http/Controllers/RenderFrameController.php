<?php

namespace App\Http\Controllers;

use App\Models\BrandProfile;
use App\Models\Post;
use App\Services\Render\TemplateRegistry;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class RenderFrameController extends Controller
{
    public function __invoke(Post $post, int $frame): Response
    {
        Inertia::setRootView('render');

        $template = TemplateRegistry::for($post->network, $post->format);
        $framesTotal = TemplateRegistry::frameCount($post);

        abort_if($frame < 1 || $frame > $framesTotal, 404);

        $post->loadMissing('contentPillar');
        $brand = BrandProfile::current();

        return Inertia::render('Render/Frame', [
            'template' => [
                'name' => $template->name,
                'component' => $template->component,
                'width' => $template->width,
                'height' => $template->height,
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
            'logoUrl' => route('brand.logo'),
            'brand' => [
                'url' => $brand->url,
                'cta' => $brand->cta,
            ],
        ]);
    }

    private function backgroundUrl(Post $post, int $frame, bool $usesAi): ?string
    {
        if (! $usesAi || ! Storage::disk('local')->exists("assets/{$post->id}/bg.png")) {
            return null;
        }

        return URL::temporarySignedRoute('assets.show', now()->addMinutes(10), [
            'post' => $post->id,
            'file' => 'bg.png',
        ]);
    }
}
