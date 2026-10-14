<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetController extends Controller
{
    private const ALLOWED_PATTERN = '/^(bg\.png|bg-alt\.png|frame-\d{2}\.png|final\.png|final\.mp4)$/';

    public function __invoke(Brand $brand, string $post, string $file): BinaryFileResponse
    {
        // Sin scope de marca: un asset de otra marca es 403, no 404.
        $post = Post::withoutGlobalScopes()->findOrFail((int) $post);
        abort_unless($post->brand_id === $brand->id, 403);
        abort_unless(preg_match(self::ALLOWED_PATTERN, $file), 404);

        $path = "assets/{$post->id}/{$file}";

        abort_unless(Storage::disk('local')->exists($path), 404);

        // BinaryFileResponse adivina el Content-Type y soporta Range (video).
        return response()->file(Storage::disk('local')->path($path));
    }
}
