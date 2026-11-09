<?php

namespace Tests\Feature;

use App\Enums\MediaMode;
use App\Enums\MediaSource;
use App\Enums\Network;
use App\Enums\PostFormat;
use App\Enums\PostStatus;
use App\Jobs\ComposeFromMedia;
use App\Models\Brand;
use App\Models\Plan;
use App\Models\Post;
use App\Services\Media\FakeMediaInspector;
use App\Services\Media\MediaInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorePostFromMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake('local');
        $this->app->instance(MediaInspector::class, new FakeMediaInspector);
    }

    private function postFromMedia(Brand $brand, array $data = [])
    {
        return $this->post(
            "/b/{$brand->slug}/plans/2026/10/posts/from-media",
            array_merge([
                'file' => UploadedFile::fake()->image('foto.jpg', 800, 600),
                'network' => 'instagram',
                'mode' => 'template',
                'date' => '2026-10-15',
            ], $data)
        );
    }

    public function test_crea_post_desde_foto_y_encola_la_redaccion(): void
    {
        $this->actingAsOperatorFor();

        $this->postFromMedia(app('brand'), ['note' => 'Foto del taller de ayer'])
            ->assertSessionHasNoErrors();

        $post = Post::withoutGlobalScopes()->latest('id')->first();

        $this->assertNotNull($post);
        $this->assertSame(MediaSource::Upload, $post->media_source);
        $this->assertSame(MediaMode::Template, $post->media_mode);
        $this->assertSame(Network::Instagram, $post->network);
        $this->assertSame(PostFormat::Image, $post->format);
        $this->assertSame(PostStatus::Drafted, $post->status);
        $this->assertSame('Analizando material…', $post->hook);
        $this->assertSame('Foto del taller de ayer', $post->operator_note);
        $this->assertNotNull($post->composing_at);
        $this->assertNotNull($post->source_path);
        $this->assertSame('image/jpeg', $post->source_mime);

        Storage::disk('local')->assertExists($post->source_path);
        Queue::assertPushed(ComposeFromMedia::class);
    }

    public function test_video_en_instagram_deriva_a_reel(): void
    {
        $this->actingAsOperatorFor();

        $this->postFromMedia(app('brand'), [
            'file' => UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4'),
            'mode' => 'raw',
        ])->assertSessionHasNoErrors();

        $post = Post::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame(PostFormat::Reel, $post->format);
        $this->assertSame(MediaMode::Raw, $post->media_mode);
    }

    public function test_cuota_agotada_devuelve_422(): void
    {
        $brand = app('brand');
        $brand->plan->update(['posts_per_month' => 0]);
        $this->actingAsOperatorFor();

        $this->postFromMedia($brand)->assertSessionHasErrors('quota');

        $this->assertSame(0, Post::withoutGlobalScopes()->count());
        Queue::assertNothingPushed();
    }

    public function test_red_fuera_del_plan_devuelve_422(): void
    {
        $brand = Brand::factory()->create([
            'plan_id' => Plan::factory()->create(['networks' => ['linkedin']]),
        ]);
        $this->actingAsOperatorFor($brand);

        $this->postFromMedia($brand, ['network' => 'instagram'])
            ->assertSessionHasErrors('network');
    }

    public function test_video_en_linkedin_devuelve_422(): void
    {
        $brand = Brand::factory()->create([
            'plan_id' => Plan::factory()->create(['networks' => ['linkedin']]),
        ]);
        $this->actingAsOperatorFor($brand);

        $this->postFromMedia($brand, [
            'file' => UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4'),
            'network' => 'linkedin',
        ])->assertSessionHasErrors('file');
    }

    public function test_imagen_en_tiktok_devuelve_422(): void
    {
        $this->actingAsOperatorFor();

        $this->postFromMedia(app('brand'), ['network' => 'tiktok'])
            ->assertSessionHasErrors('file');
    }

    public function test_fecha_fuera_del_mes_devuelve_422(): void
    {
        $this->actingAsOperatorFor();

        $this->postFromMedia(app('brand'), ['date' => '2026-11-05'])
            ->assertSessionHasErrors('date');
    }

    public function test_video_mas_largo_de_lo_permitido_devuelve_422(): void
    {
        $this->app->instance(
            MediaInspector::class,
            (new FakeMediaInspector)->withMeta([
                'mime' => 'video/mp4',
                'duration' => 120,
            ])
        );
        $this->actingAsOperatorFor();

        $this->postFromMedia(app('brand'), [
            'file' => UploadedFile::fake()->create('largo.mp4', 2048, 'video/mp4'),
        ])->assertSessionHasErrors('file');
    }

    public function test_operador_de_otra_marca_recibe_403(): void
    {
        $other = Brand::factory()->create();
        $this->actingAsOperatorFor(); // asignado a sabere, no a $other

        // SetActiveBrand + BrandPolicy deniegan con 403 (no 404).
        $this->postFromMedia($other)->assertForbidden();
    }

    public function test_reintento_compose_reencola_con_descripcion(): void
    {
        $this->actingAsOperatorFor();
        Storage::disk('local')->put('x/source.jpg', 'fake');

        $post = Post::factory()->create([
            'media_source' => MediaSource::Upload,
            'media_mode' => MediaMode::Template,
            'source_path' => 'x/source.jpg',
            'source_mime' => 'image/jpeg',
            'media_description' => ['description' => 'Un taller'],
            'compose_error' => 'Falló la IA',
            'composing_at' => null,
        ]);

        $this->post("/b/sabere/posts/{$post->id}/compose")
            ->assertSessionHasNoErrors();

        $this->assertNotNull($post->fresh()->composing_at);
        $this->assertNull($post->fresh()->compose_error);
        Queue::assertPushed(
            ComposeFromMedia::class,
            fn (ComposeFromMedia $job) => $job->reuseDescription === true
        );
    }

    public function test_compose_rechaza_post_que_no_es_upload(): void
    {
        $this->actingAsOperatorFor();

        $post = Post::factory()->create(['media_source' => MediaSource::Ai]);

        $this->post("/b/sabere/posts/{$post->id}/compose")->assertStatus(422);
    }
}
