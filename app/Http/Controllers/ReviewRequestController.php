<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Enums\ReviewDecision;
use App\Mail\ReviewRequestMail;
use App\Models\Brand;
use App\Models\Post;
use App\Models\ReviewRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ReviewRequestController extends Controller
{
    /**
     * Enviar posts renderizados a aprobación del cliente.
     * Sin `week_from` cubre el mes completo.
     */
    public function store(Request $request, Brand $brand, int $year, int $month): RedirectResponse
    {
        abort_unless($brand->requires_client_approval, 422, 'Esta marca no requiere aprobación del cliente.');

        $validated = $request->validate([
            'week_from' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $from = isset($validated['week_from'])
            ? CarbonImmutable::parse($validated['week_from'])
            : CarbonImmutable::create($year, $month, 1);
        $to = isset($validated['week_from'])
            ? $from->endOfWeek()
            : $from->endOfMonth();

        // Solo los rendered del rango que no estén ya en otra revisión abierta.
        $busyPostIds = ReviewRequest::open()
            ->join('review_items', 'review_items.review_request_id', '=', 'review_requests.id')
            ->pluck('review_items.post_id');

        $posts = Post::where('status', PostStatus::Rendered)
            ->whereBetween('scheduled_at', [$from->startOfDay(), $to->endOfDay()])
            ->whereNotIn('id', $busyPostIds)
            ->orderBy('scheduled_at')
            ->get();

        if ($posts->isEmpty()) {
            throw ValidationException::withMessages([
                'review' => 'No hay diseños listos para revisar.',
            ]);
        }

        $contacts = $brand->contacts;

        $review = new ReviewRequest([
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'expires_at' => now()->addDays(14),
            'sent_to' => $contacts->pluck('email'),
            'created_by' => $request->user()->id,
        ]);
        $token = $review->generateToken();
        $review->save();

        // week_from = lunes de la semana del post, recortado al inicio del
        // rango para la primera semana del mes (igual que weeksOf).
        foreach ($posts as $post) {
            $review->items()->create([
                'post_id' => $post->id,
                'decision' => ReviewDecision::Pending,
                'week_from' => $post->scheduled_at->startOfWeek()->max($from)->toDateString(),
            ]);
        }

        $this->sendMails($review, $token, $posts->count());

        return back()->with([
            'success' => 'Revisión enviada a los contactos de la marca.',
            'review_url' => route('review.show', ['token' => $token]),
        ]);
    }

    public function revoke(Brand $brand, ReviewRequest $review): RedirectResponse
    {
        abort_unless($review->isOpen(), 422, 'La revisión ya no está abierta.');

        $review->update(['revoked_at' => now()]);

        return back()->with('success', 'Revisión revocada. El enlace deja de funcionar.');
    }

    /**
     * Nuevo enlace: rota el token, renueva la expiración y reenvía el correo.
     */
    public function rotate(Brand $brand, ReviewRequest $review): RedirectResponse
    {
        abort_unless($review->isOpen(), 422, 'La revisión ya no está abierta.');

        $token = $review->generateToken();
        $review->expires_at = now()->addDays(14);
        $review->save();

        $this->sendMails($review, $token, $review->items()->count());

        return back()->with([
            'success' => 'Nuevo enlace generado y reenviado.',
            'review_url' => route('review.show', ['token' => $token]),
        ]);
    }

    private function sendMails(ReviewRequest $review, string $token, int $postsCount): void
    {
        $url = route('review.show', ['token' => $token]);

        foreach ($review->brand->contacts as $contact) {
            Mail::to($contact->email)->queue(
                new ReviewRequestMail($review, $url, $postsCount)
            );
        }
    }
}
