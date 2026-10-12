<?php

namespace Tests\Feature;

use App\Services\Images\ImageGenerationException;
use App\Services\Images\PixabayPhotoSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PixabayPhotoSourceTest extends TestCase
{
    private function hit(array $overrides = []): array
    {
        return array_merge([
            'id' => 123,
            'imageWidth' => 4000,
            'imageHeight' => 3000,
            'largeImageURL' => 'https://pixabay.com/get/foto-1280.jpg',
            'pageURL' => 'https://pixabay.com/photos/foto-123/',
            'user' => 'fotografo',
            'tags' => 'school, hallway, lockers, education',
        ], $overrides);
    }

    private function fakeJpeg(): string
    {
        $img = imagecreatetruecolor(2000, 1200);
        ob_start();
        imagejpeg($img, null, 80);

        return ob_get_clean();
    }

    public function test_returns_normalized_png_with_meta(): void
    {
        config()->set('images.pixabay.api_key', 'pix-key');

        Http::fake([
            'pixabay.com/api*' => Http::response(['hits' => [$this->hit()]]),
            'pixabay.com/get/*' => Http::response($this->fakeJpeg()),
        ]);

        $result = (new PixabayPhotoSource)->find('school hallway', 1200, 660, 42);

        $this->assertNotNull($result);
        $size = getimagesizefromstring($result->bytes);
        $this->assertSame([1200, 660], [$size[0], $size[1]]);
        $this->assertSame('image/png', $size['mime']);
        $this->assertSame('pixabay', $result->meta['source']);
        $this->assertSame(123, $result->meta['id']);
        $this->assertSame('fotografo', $result->meta['user']);
        $this->assertSame(0, $result->meta['skip']);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://pixabay.com/api/')
            && str_contains($request->url(), 'image_type=photo')
            && str_contains($request->url(), 'orientation=horizontal'));
    }

    public function test_excludes_hits_with_children_tags(): void
    {
        Http::fake([
            'pixabay.com/api*' => Http::response(['hits' => [
                $this->hit(['id' => 1, 'tags' => 'school, children, classroom']),
                $this->hit(['id' => 2, 'tags' => 'kids, playing']),
                $this->hit(['id' => 3, 'tags' => 'school, hallway, lockers']),
            ]]),
            'pixabay.com/get/*' => Http::response($this->fakeJpeg()),
        ]);

        $result = (new PixabayPhotoSource)->find('school', 1200, 660, 0);

        // Solo queda el hit 3 → el índice (0+0)%1 = 0 lo elige.
        $this->assertSame(3, $result->meta['id']);
    }

    public function test_deterministic_choice_by_seed_and_skip(): void
    {
        Http::fake([
            'pixabay.com/api*' => Http::response(['hits' => [
                $this->hit(['id' => 10, 'tags' => 'school, desks']),
                $this->hit(['id' => 11, 'tags' => 'school, board']),
                $this->hit(['id' => 12, 'tags' => 'school, yard']),
            ]]),
            'pixabay.com/get/*' => Http::response($this->fakeJpeg()),
        ]);

        $source = new PixabayPhotoSource;

        $this->assertSame(11, $source->find('school', 1200, 660, seed: 1)->meta['id']);
        $this->assertSame(12, $source->find('school', 1200, 660, seed: 1, skip: 1)->meta['id']);
        $this->assertSame(10, $source->find('school', 1200, 660, seed: 1, skip: 2)->meta['id']);
    }

    public function test_higher_score_wins_over_order(): void
    {
        Http::fake([
            'pixabay.com/api*' => Http::response(['hits' => [
                $this->hit(['id' => 20, 'tags' => 'school, hallway']),      // score 2 pero primero
                $this->hit(['id' => 21, 'tags' => 'beach, sunset']),       // score 0
                $this->hit(['id' => 22, 'tags' => 'school, hallway, lockers']), // score 3
            ]]),
            'pixabay.com/get/*' => Http::response($this->fakeJpeg()),
        ]);

        $result = (new PixabayPhotoSource)->find('school hallway lockers', 1200, 660, 0);

        $this->assertSame(22, $result->meta['id']);
        $this->assertSame(3, $result->meta['score']);
    }

    public function test_returns_null_when_all_scores_are_zero(): void
    {
        Http::fake([
            'pixabay.com/api*' => Http::response(['hits' => [
                $this->hit(['id' => 30, 'tags' => 'cat, pet']),
                $this->hit(['id' => 31, 'tags' => 'car, wheel']),
            ]]),
        ]);

        $this->assertNull((new PixabayPhotoSource)->find('school hallway', 1200, 660, 1));
    }

    public function test_seed_and_skip_rotate_only_between_top_scorers(): void
    {
        Http::fake([
            'pixabay.com/api*' => Http::response(['hits' => [
                $this->hit(['id' => 40, 'tags' => 'school, hallway']), // score 2
                $this->hit(['id' => 41, 'tags' => 'school']),          // score 1
                $this->hit(['id' => 42, 'tags' => 'school, hallway']), // score 2
            ]]),
            'pixabay.com/get/*' => Http::response($this->fakeJpeg()),
        ]);

        $source = new PixabayPhotoSource;

        // Empatados a 2: ids 40 y 42; el 41 nunca sale.
        $this->assertSame(40, $source->find('school hallway', 1200, 660, seed: 0)->meta['id']);
        $this->assertSame(42, $source->find('school hallway', 1200, 660, seed: 0, skip: 1)->meta['id']);
        $this->assertSame(40, $source->find('school hallway', 1200, 660, seed: 0, skip: 2)->meta['id']);
    }

    public function test_returns_null_without_hits(): void
    {
        Http::fake([
            'pixabay.com/api*' => Http::response(['hits' => []]),
        ]);

        $this->assertNull((new PixabayPhotoSource)->find('zzqx', 1200, 660, 1));
    }

    public function test_throws_on_http_error(): void
    {
        Http::fakeSequence('pixabay.com/api*')
            ->push('error', 500)
            ->push('error', 500);

        $this->expectException(ImageGenerationException::class);
        (new PixabayPhotoSource)->find('school', 1200, 660, 1);
    }
}
