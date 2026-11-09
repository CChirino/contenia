<?php

namespace App\Http\Controllers;

use App\Enums\MediaMode;
use App\Enums\MediaSource;
use App\Enums\Network;
use App\Enums\PostFormat;
use App\Enums\PostStatus;
use App\Enums\QuotaAction;
use App\Http\Requests\RegeneratePostRequest;
use App\Http\Requests\StorePostFromMediaRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Jobs\ComposeFromMedia;
use App\Jobs\RegeneratePost;
use App\Jobs\RenderPost;
use App\Models\Brand;
use App\Models\MonthlyPlan;
use App\Models\Post;
use App\Models\PostEdit;
use App\Models\ReviewRequest;
use App\Services\Media\MediaInspector;
use App\Services\Quota\QuotaGuard;
use App\Services\Render\TemplateRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class PostController extends Controller
{
    public function update(UpdatePostRequest $request, Brand $brand, Post $post): RedirectResponse
    {
        abort_unless(
            in_array($post->status, [PostStatus::Drafted, PostStatus::Approved, PostStatus::Rendered, PostStatus::ClientApproved], true),
            422,
            'Solo se puede editar un post en estado borrador, aprobado, renderizado o aprobado por el cliente.'
        );

        $hadRender = $post->rendered_at !== null;
        // Editar un renderizado (o ya aprobado por el cliente) lo devuelve a
        // aprobado: se re-renderiza y vuelve a la revisión del cliente.
        $wasRendered = in_array($post->status, [PostStatus::Rendered, PostStatus::ClientApproved], true);

        $validated = $request->validated();

        // Memoria (spec §8.2): diffs de hook/copy como evidencia para
        // DistillFeedback. Solo se registran los campos que cambian.
        $changes = [];
        foreach (['hook', 'copy'] as $field) {
            if (array_key_exists($field, $validated)
                && (string) $validated[$field] !== (string) $post->{$field}) {
                $changes[$field] = [(string) $post->{$field}, (string) $validated[$field]];
            }
        }

        $post->update($validated);

        if ($changes !== []) {
            $reviewRequestId = $this->reviewRequestFor($post);

            foreach ($changes as $field => [$before, $after]) {
                PostEdit::create([
                    'post_id' => $post->id,
                    'user_id' => $request->user()->id,
                    'field' => $field,
                    'before' => Str::limit($before, 1000, ''),
                    'after' => Str::limit($after, 1000, ''),
                    'review_request_id' => $reviewRequestId,
                ]);
            }
        }

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
            $post->update([
                'status' => $post->brand->requires_client_approval
                    ? PostStatus::Rendered
                    : PostStatus::ClientApproved,
            ]);
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

        // Un fondo nuevo IA consume cupo del mes de contenido del post.
        if ($request->boolean('fresh_background')) {
            app(QuotaGuard::class)->check(
                $post->brand, QuotaAction::ImageRefreshes,
                $post->monthlyPlan->year, $post->monthlyPlan->month
            );
        }

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

    /**
     * Crea un post "desde archivo": el operador sube la foto/video y la
     * IA redacta hook/copy/hashtags (job ComposeFromMedia). El formato se
     * deriva del tipo de archivo y la red.
     */
    public function storeFromMedia(StorePostFromMediaRequest $request, Brand $brand, int $year, int $month, MediaInspector $inspector): RedirectResponse
    {
        $data = $request->validated();
        $file = $request->file('file');
        $mime = $file->getMimeType();
        $isVideo = str_starts_with($mime, 'video/');
        $network = Network::from($data['network']);

        // La red debe estar permitida por el plan de la marca.
        if (! app(QuotaGuard::class)->allowsNetwork($brand, $network)) {
            throw ValidationException::withMessages([
                'network' => 'La red elegida no está permitida por el plan de esta marca.',
            ]);
        }

        // Formato derivado: imagen → image; video → reel (Instagram) o
        // video (TikTok). Las combinaciones imposibles devuelven 422.
        if ($isVideo && $network === Network::LinkedIn) {
            throw ValidationException::withMessages([
                'file' => 'LinkedIn no admite video en esta aplicación.',
            ]);
        }

        if (! $isVideo && $network === Network::TikTok) {
            throw ValidationException::withMessages([
                'file' => 'TikTok solo admite video.',
            ]);
        }

        $format = $isVideo
            ? ($network === Network::TikTok ? PostFormat::Video : PostFormat::Reel)
            : PostFormat::Image;

        // Tamaño por tipo: las imágenes tienen un tope menor que el video.
        if (! $isVideo
            && $file->getSize() > (int) config('media.image_max_mb', 10) * 1024 * 1024) {
            throw ValidationException::withMessages([
                'file' => 'La imagen supera el máximo de '.config('media.image_max_mb').' MB.',
            ]);
        }

        // La fecha debe pertenecer al año/mes de la grilla.
        $date = CarbonImmutable::parse($data['date'], 'America/Caracas');

        if ($date->year !== $year || $date->month !== $month) {
            throw ValidationException::withMessages([
                'date' => 'La fecha debe pertenecer al mes de la grilla.',
            ]);
        }

        // Probe temprano: metadatos y, en video, la duración máxima.
        $meta = $inspector->probe($file->getRealPath());

        if ($isVideo
            && ($meta['duration'] ?? 0) > (float) config('media.video_max_seconds', 90)) {
            throw ValidationException::withMessages([
                'file' => 'El video supera la duración máxima de '.config('media.video_max_seconds').' segundos.',
            ]);
        }

        // Cupo de posts del mes antes de crear nada.
        app(QuotaGuard::class)->check($brand, QuotaAction::PostsGenerated, $year, $month);

        $plan = MonthlyPlan::firstOrCreate(
            ['year' => $year, 'month' => $month],
            ['status' => 'draft']
        );

        $post = $plan->posts()->create([
            'scheduled_at' => $date->setTime(match ($network) {
                Network::LinkedIn => 8,
                Network::Instagram => 18,
                Network::TikTok => 19,
            }, 0),
            'network' => $network,
            'format' => $format,
            'status' => PostStatus::Drafted,
            'hook' => 'Analizando material…',
            'copy' => '',
            'hashtags' => [],
            'design_payload' => ['slides' => []],
            'template' => TemplateRegistry::for($network, $format)->name,
            'media_source' => MediaSource::Upload,
            'media_mode' => MediaMode::from($data['mode']),
            'operator_note' => $data['note'] ?? null,
            'composing_at' => now(),
        ]);

        // El archivo vive junto al resto de assets del post (disco local).
        $ext = $file->getClientOriginalExtension() ?: ($isVideo ? 'mp4' : 'jpg');
        $stored = Storage::disk('local')->putFileAs($post->assetDir(), $file, "source.{$ext}");

        $post->update([
            'source_path' => $stored,
            'source_mime' => $mime,
            'source_meta' => $meta,
        ]);

        ComposeFromMedia::dispatch($post->fresh());

        return back()->with('success', 'Post desde archivo encolado: la IA está redactando el contenido.');
    }

    /**
     * Reintenta la redacción de un post desde archivo (p. ej. tras un
     * compose_error). Reutiliza la descripción de visión si ya existe.
     */
    public function compose(Brand $brand, Post $post): RedirectResponse
    {
        abort_unless($post->media_source === MediaSource::Upload, 422, 'Este post no fue creado desde un archivo.');
        abort_if($post->composing_at !== null, 409, 'Ya hay una redacción en curso para este post.');

        $post->update(['composing_at' => now(), 'compose_error' => null]);

        ComposeFromMedia::dispatch($post->fresh(), $post->media_description !== null);

        return back()->with('success', 'Redacción del post encolada.');
    }

    /**
     * Revisión que incluye el post: la abierta si la hay, o la más reciente.
     * Sirve para asignar las ediciones a un periodo de feedback.
     */
    private function reviewRequestFor(Post $post): ?int
    {
        $requests = ReviewRequest::whereHas(
            'items',
            fn ($q) => $q->where('post_id', $post->id)
        )->orderByDesc('id')->get();

        return ($requests->first(fn (ReviewRequest $r) => $r->isOpen())
            ?? $requests->first())?->id;
    }

    public function regenerate(RegeneratePostRequest $request, Brand $brand, Post $post): RedirectResponse
    {
        abort_unless(
            $post->status === PostStatus::Drafted,
            422,
            'Solo se puede regenerar un post en estado borrador.'
        );

        // Cupo de regeneraciones del mes de contenido del post.
        app(QuotaGuard::class)->check(
            $post->brand, QuotaAction::Regenerations,
            $post->monthlyPlan->year, $post->monthlyPlan->month
        );

        // Posts desde archivo: la regeneración reescribe hook/copy con
        // ComposeFromMedia reutilizando la descripción de visión (misma
        // cuota de regeneraciones).
        if ($post->media_source === MediaSource::Upload) {
            abort_if($post->composing_at !== null, 409, 'Ya hay una redacción en curso para este post.');

            $post->update(['composing_at' => now()]);
            ComposeFromMedia::dispatch($post, true, $request->validated('comment'));

            return back()->with('success', 'Reescritura del post encolada.');
        }

        RegeneratePost::dispatch($post, $request->validated('comment'));

        return back()->with('success', 'Regeneración del post encolada.');
    }
}
