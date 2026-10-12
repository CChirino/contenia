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
        config()->set('images.pollinations.model', 'z-image-turbo');

        Http::fake([
            'gen.pollinations.ai/*' => Http::response($this->fakePng()),
        ]);

        $bytes = (new PollinationsGenerator)->generate('un aula iluminada', 1200, 1200, 42);

        $size = getimagesizefromstring($bytes);
        $this->assertSame([1200, 1200], [$size[0], $size[1]]);
        $this->assertSame('image/png', $size['mime']);

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bearer pol-key')
                && str_starts_with($request->url(), 'https://gen.pollinations.ai/image/')
                && str_contains($request->url(), 'model=z-image-turbo')
                && str_contains($request->url(), 'width=1200')
                && str_contains($request->url(), 'height=1200')
                && str_contains($request->url(), 'seed=42')
                && ! str_contains($request->url(), 'nologo')
                && ! str_contains($request->url(), 'enhance');
        });
    }

    public function test_rounds_dimensions_up_to_multiple_of_16(): void
    {
        Http::fake([
            'gen.pollinations.ai/*' => Http::response($this->fakePng()),
        ]);

        // 1080×1350 no son múltiplos de 16 → pide 1088×1360 y recorta a lo exacto.
        $bytes = (new PollinationsGenerator)->generate('p', 1080, 1350, 7);

        $size = getimagesizefromstring($bytes);
        $this->assertSame([1080, 1350], [$size[0], $size[1]]);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'width=1088')
            && str_contains($request->url(), 'height=1360'));
    }

    public function test_retries_on_429_and_then_succeeds(): void
    {
        Http::fakeSequence('gen.pollinations.ai/*')
            ->push('busy', 429)
            ->push($this->fakePng());

        $bytes = (new PollinationsGenerator)->generate('p', 100, 100, 1);

        $size = getimagesizefromstring($bytes);
        $this->assertSame([100, 100], [$size[0], $size[1]]);
        Http::assertSentCount(2);
    }

    public function test_http_error_throws_exception_with_api_message(): void
    {
        config()->set('images.pollinations.api_key', 'pol-key');

        Http::fake([
            'gen.pollinations.ai/*' => Http::response(
                ['success' => false, 'error' => ['message' => 'modelo saturado', 'code' => 'UPSTREAM']],
                500
            ),
        ]);

        try {
            (new PollinationsGenerator)->generate('p', 100, 100, 1);
            $this->fail('No lanzó ImageGenerationException');
        } catch (ImageGenerationException $e) {
            $this->assertStringContainsString('modelo saturado', $e->getMessage());
            $this->assertStringNotContainsString('"success"', $e->getMessage());
        }
    }
}
