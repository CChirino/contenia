<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    private const ALLOWED_PATTERN = '/^(bg\.png|bg-alt\.png|frame-\d{2}\.png|final\.png|final\.mp4)$/';

    public function __invoke(Post $post, string $file): StreamedResponse
    {
        abort_unless(preg_match(self::ALLOWED_PATTERN, $file), 404);

        $path = "assets/{$post->id}/{$file}";

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
