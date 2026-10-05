<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Http\Requests\RegeneratePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Jobs\RegeneratePost;
use App\Jobs\RenderPost;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        abort_unless(
            in_array($post->status, [PostStatus::Drafted, PostStatus::Approved, PostStatus::Rendered], true),
            422,
            'Solo se puede editar un post en estado borrador, aprobado o renderizado.'
        );

        $wasRendered = $post->status === PostStatus::Rendered;

        $post->update($request->validated());

        // Editar un post ya renderizado lo devuelve a aprobado y re-renderiza.
        if ($wasRendered) {
            $post->update(['status' => PostStatus::Approved]);
            RenderPost::dispatch($post->fresh());
        }

        return back();
    }

    public function approve(Post $post): RedirectResponse
    {
        abort_unless(
            $post->status->canTransitionTo(PostStatus::Approved),
            422,
            'Solo se puede aprobar un post en estado borrador.'
        );

        $post->update(['status' => PostStatus::Approved]);

        RenderPost::dispatch($post->fresh());

        return back();
    }

    public function render(Request $request, Post $post): RedirectResponse
    {
        abort_unless(
            in_array($post->status, [PostStatus::Approved, PostStatus::Rendered], true),
            422,
            'Solo se puede renderizar un post aprobado o ya renderizado.'
        );

        RenderPost::dispatch($post, $request->boolean('fresh_background'));

        return back()->with('success', 'Render del diseño encolado.');
    }

    public function regenerate(RegeneratePostRequest $request, Post $post): RedirectResponse
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
