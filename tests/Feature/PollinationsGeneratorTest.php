<?php

namespace Tests\Feature;

use App\Services\Images\ImageGenerationException;
use App\Services\Images\PollinationsGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PollinationsGeneratorTest extends TestCase
{
    private function fakePng(): string
    {
        // PNG 1x1 transparente.
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    public function test_returns_image_bytes(): void
    {
        config()->set('images.pollinations.api_key', 'pol-key');
        config()->set('images.pollinations.model', 'flux');

        Http::fake([
            'image.pollinations.ai/*' => Http::response($this->fakePng()),
        ]);

        $bytes = (new PollinationsGenerator)->generate('un aula iluminada', 1200, 1200, 42);

        $size = getimagesizefromstring($bytes);
        $this->assertSame([1200, 1200], [$size[0], $size[1]]);
        $this->assertSame('image/png', $size['mime']);

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bearer pol-key')
                && str_starts_with($request->url(), 'https://image.pollinations.ai/prompt/')
                && str_contains($request->url(), 'model=flux')
                && str_contains($request->url(), 'width=1200')
                && str_contains($request->url(), 'height=1200')
                && str_contains($request->url(), 'seed=42')
                && str_contains($request->url(), 'nologo=true');
        });
    }

    public function test_retries_on_429_and_then_succeeds(): void
    {
        Http::fakeSequence('image.pollinations.ai/*')
            ->push('busy', 429)
            ->push($this->fakePng());

        $bytes = (new PollinationsGenerator)->generate('p', 100, 100, 1);

        $size = getimagesizefromstring($bytes);
        $this->assertSame([100, 100], [$size[0], $size[1]]);
        Http::assertSentCount(2);
    }

    public function test_http_error_throws_exception(): void
    {
        config()->set('images.pollinations.api_key', 'pol-key');

        Http::fake([
            'image.pollinations.ai/*' => Http::response('boom', 500),
        ]);

        $this->expectException(ImageGenerationException::class);

        (new PollinationsGenerator)->generate('p', 100, 100, 1);
    }
}
