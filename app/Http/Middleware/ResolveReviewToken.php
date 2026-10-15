<?php

namespace App\Http\Middleware;

use App\Models\ReviewRequest;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portal público del cliente: resuelve {token} a la solicitud de revisión
 * (sin auth ni marca activa; findByToken fija la marca del token).
 *
 * - Token inexistente → 404.
 * - Revocado / vencido → vista Review/Unavailable.
 * - Enviada → GET en solo lectura; POST → 409.
 */
class ResolveReviewToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $review = ReviewRequest::findByToken((string) $request->route('token'));

        if ($review === null) {
            abort(404);
        }

        app()->instance('review', $review);

        if ($review->revoked_at !== null) {
            return $this->unavailable('revoked');
        }

        if ($review->expires_at->isPast()) {
            return $this->unavailable('expired');
        }

        if ($review->submitted_at !== null && ! $request->isMethod('GET')) {
            abort(409);
        }

        return $next($request);
    }

    private function unavailable(string $reason): Response
    {
        return Inertia::render('Review/Unavailable', ['reason' => $reason])
            ->toResponse(request())
            ->setStatusCode(200);
    }
}
