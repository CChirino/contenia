<?php

namespace App\Http\Controllers;

use App\Enums\PostFormat;
use App\Enums\PostStatus;
use App\Enums\ReviewDecision;
use App\Events\ReviewSubmitted;
use App\Models\Post;
use App\Models\ReviewItem;
use App\Models\ReviewRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReviewPortalController extends Controller
{
    public function show(): Response
    {
        /** @var ReviewRequest $review */
        $review = app('review');
        $review->loadMissing(['items.post', 'weekComments']);

        $itemsByWeek = $review->items->sortBy('post.scheduled_at')->groupBy(
            fn (ReviewItem $item) => $item->week_from->format('Y-m-d')
        );

        $comments = $review->weekComments->keyBy(
            fn ($c) => $c->week_from->format('Y-m-d')
        );

        $weeks = $itemsByWeek->map(function ($items, string $weekFrom) use ($review, $comments) {
            return [
                'week_from' => $weekFrom,
                'week_to' => $items->first()->week_from->endOfWeek()->format('Y-m-d'),
                'comment' => $comments->get($weekFrom)?->comment,
                'posts' => $items->map(fn (ReviewItem $item) => $this->postPayload($review, $item))->values(),
            ];
        })->values();

        return Inertia::render('Review/Show', [
            'brand' => [
                'name' => $review->brand->name,
                'logo_url' => route('brands.logo', ['brand' => $review->brand->slug]),
                'palette' => $review->brand->palette,
            ],
            'review' => [
                'from_date' => $review->from_date->format('Y-m-d'),
                'to_date' => $review->to_date->format('Y-m-d'),
                'expires_at' => $review->expires_at->format('Y-m-d'),
                'submitted_at' => $review->submitted_at?->toIso8601String(),
                'general_comment' => $review->general_comment,
                'summary' => $review->summary(),
                'token' => request()->route('token'),
            ],
            'weeks' => $weeks,
        ]);
    }

    public function item(Request $request, string $token, Post $post): RedirectResponse
    {
        $review = $this->review();

        $item = $review->items()->where('post_id', $post->id)->firstOrFail();

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'comment' => [
                Rule::requiredIf($request->input('decision') === 'rejected'),
                'nullable', 'string', 'max:2000',
            ],
        ], [
            'comment.required' => 'Indica el motivo del rechazo.',
        ]);

        $item->update([
            'decision' => $validated['decision'],
            'comment' => $validated['decision'] === 'rejected' ? $validated['comment'] : null,
            'decided_at' => now(),
        ]);

        return back();
    }

    public function approveWeek(string $token, string $week_from): RedirectResponse
    {
        $this->review()->items()
            ->where('week_from', $week_from)
            ->whereIn('decision', [ReviewDecision::Pending, ReviewDecision::Rejected])
            ->update([
                'decision' => ReviewDecision::Approved->value,
                'comment' => null,
                'decided_at' => now(),
            ]);

        return back();
    }

    public function weekComment(Request $request, string $token, string $week_from): RedirectResponse
    {
        $validated = $request->validate(['comment' => ['required', 'string', 'max:2000']]);

        $this->review()->weekComments()->updateOrCreate(
            ['week_from' => $week_from],
            ['comment' => $validated['comment']],
        );

        return back();
    }

    public function approveAll(string $token): RedirectResponse
    {
        $this->review()->items()
            ->whereIn('decision', [ReviewDecision::Pending, ReviewDecision::Rejected])
            ->update([
                'decision' => ReviewDecision::Approved->value,
                'comment' => null,
                'decided_at' => now(),
            ]);

        return back();
    }

    /**
     * Cierre de la revisión: aplica decisiones a los posts (en transacción)
     * y marca la solicitud como enviada.
     */
    public function submit(Request $request, string $token): RedirectResponse
    {
        $review = $this->review();

        $validated = $request->validate(['general_comment' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(function () use ($review, $validated) {
            foreach ($review->items()->with('post')->get() as $item) {
                $post = $item->post;

                // Si el operador lo editó mientras tanto, ya no está en
                // rendered: se omite sin error.
                if ($post->status !== PostStatus::Rendered) {
                    continue;
                }

                if ($item->decision === ReviewDecision::Approved) {
                    $post->update(['status' => PostStatus::ClientApproved]);
                } elseif ($item->decision === ReviewDecision::Rejected) {
                    // Conserva assets y rendered_at: el rechazo lo devuelve
                    // a borrador para que el equipo lo retrabaje.
                    $post->update(['status' => PostStatus::Drafted]);
                }
            }

            $review->update([
                'submitted_at' => now(),
                'general_comment' => $validated['general_comment'] ?? null,
            ]);
        });

        event(new ReviewSubmitted($review));

        return redirect()->route('review.show', ['token' => $token]);
    }

    private function review(): ReviewRequest
    {
        return app('review');
    }

    private function postPayload(ReviewRequest $review, ReviewItem $item): array
    {
        $post = $item->post;
        $expires = $review->expires_at;

        $mediaUrl = fn (string $file) => URL::temporarySignedRoute('assets.show', $expires, [
            'brand' => $review->brand->slug,
            'post' => $post->id,
            'file' => $file,
        ]);

        $frames = collect($post->frames ?? [])
            ->filter(fn ($f) => Storage::disk('local')->exists($post->assetPath($f)))
            ->map($mediaUrl)
            ->values();

        $hasVideo = Storage::disk('local')->exists($post->assetPath('final.mp4'));

        $type = match (true) {
            in_array($post->format, [PostFormat::Reel, PostFormat::Video], true) && $hasVideo => 'video',
            $post->format === PostFormat::Carousel => 'carousel',
            default => 'image',
        };

        return [
            'id' => $post->id,
            'network' => $post->network->value,
            'format' => $post->format->value,
            'scheduled_date' => $post->scheduled_at->format('Y-m-d'),
            'scheduled_time' => $post->scheduled_at->format('H:i'),
            'hook' => $post->hook,
            'copy' => $post->copy,
            'hashtags' => $post->hashtags,
            'decision' => $item->decision->value,
            'comment' => $item->comment,
            'media' => [
                'type' => $type,
                'urls' => $type === 'video'
                    ? [$mediaUrl('final.mp4')]
                    : $frames->all(),
            ],
        ];
    }
}
