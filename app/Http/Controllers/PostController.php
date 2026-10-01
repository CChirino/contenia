<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Http\Requests\RegeneratePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Jobs\RegeneratePost;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;

class PostController extends Controller
{
    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        abort_unless(
            in_array($post->status, [PostStatus::Drafted, PostStatus::Approved], true),
            422,
            'Solo se puede editar un post en estado borrador o aprobado.'
        );

        $post->update($request->validated());

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

        return back();
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
