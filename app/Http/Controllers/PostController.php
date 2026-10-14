<?php

namespace App\Http\Controllers;

use App\Enums\PostFormat;
use App\Enums\PostStatus;
use App\Http\Requests\RegeneratePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Jobs\RegeneratePost;
use App\Jobs\RenderPost;
use App\Models\Brand;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class PostController extends Controller
{
    public function update(UpdatePostRequest $request, Brand $brand, Post $post): RedirectResponse
    {
        abort_unless(
            in_array($post->status, [PostStatus::Drafted, PostStatus::Approved, PostStatus::Rendered], true),
            422,
            'Solo se puede editar un post en estado borrador, aprobado o renderizado.'
        );

        $hadRender = $post->rendered_at !== null;
        $wasRendered = $post->status === PostStatus::Rendered;

        $post->update($request->validated());

        // Editar un post con render previo lo re-renderiza; si estaba
        // renderizado vuelve a aprobado (un borrador queda en borrador).
        if ($hadRender) {
            $post->update([
                'status' => $wasRendered ? PostStatus::Approved : $post->status,
                'render_requested_at' => now(),
            ]);
            RenderPost::dispatch($post->fresh());
        }

        return back();
    }

    public function approve(Brand $brand, Post $post): RedirectResponse
    {
        abort_unless(
            $post->status->canTransitionTo(PostStatus::Approved),
            422,
            'Solo se puede aprobar un post en estado borrador.'
        );

        // Si ya hay un render fresco (posterior al último cambio del
        // contenido) se pasa directo a rendered sin re-renderizar.
        $hasFreshRender = $post->rendered_at !== null
            && $post->rendered_at->gte($post->updated_at);

        if ($hasFreshRender) {
            $post->update(['status' => PostStatus::Rendered]);
        } else {
            $post->update([
                'status' => PostStatus::Approved,
                'render_requested_at' => now(),
            ]);
            RenderPost::dispatch($post->fresh());
        }

        return back();
    }

    public function render(Request $request, Brand $brand, Post $post): RedirectResponse
    {
        abort_unless(
            in_array($post->status, [PostStatus::Drafted, PostStatus::Approved, PostStatus::Rendered], true),
            422,
            'Solo se puede renderizar un post en borrador, aprobado o renderizado.'
        );

        $post->update(['render_requested_at' => now()]);

        RenderPost::dispatch($post, $request->boolean('fresh_background'));

        return back()->with('success', 'Render del diseño encolado.');
    }

    public function download(Brand $brand, Post $post): BinaryFileResponse|StreamedResponse
    {
        // Video: el MP4 es la descarga principal.
        $final = $post->assetPath('final.mp4');

        if (in_array($post->format, [PostFormat::Reel, PostFormat::Video], true)
            && Storage::disk('local')->exists($final)) {
            return Storage::disk('local')->download($final, "post-{$post->id}.mp4");
        }

        $frames = collect($post->frames ?? [])
            ->filter(fn ($file) => Storage::disk('local')->exists($post->assetPath($file)))
            ->values();

        abort_if($frames->isEmpty(), 404, 'El post no tiene frames renderizados.');

        if ($frames->count() === 1) {
            return Storage::disk('local')->download(
                $post->assetPath($frames->first()),
                "post-{$post->id}.png",
            );
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'frames').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($frames as $file) {
            $zip->addFile(Storage::disk('local')->path($post->assetPath($file)), $file);
        }

        $zip->close();

        return response()->download($zipPath, "post-{$post->id}-frames.zip")->deleteFileAfterSend();
    }

    public function regenerate(RegeneratePostRequest $request, Brand $brand, Post $post): RedirectResponse
    {
        abort_unless(
            $post->status === PostStatus::Drafted,
            422,
            'Solo se puede regenerar un post en estado borrador.'
        );

        RegeneratePost::dispatch($post, $request->validated('comment'));

        return back()->with('success', 'Regeneración del post encolada.');
    }
}
