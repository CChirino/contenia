<?php

namespace Tests\Feature;

use App\Enums\MediaMode;
use App\Enums\MediaSource;
use App\Enums\Network;
use App\Enums\PostFormat;
use App\Enums\PostStatus;
use App\Jobs\ComposeFromMedia;
use App\Models\ContentPillar;
use App\Models\LlmLog;
use App\Models\MonthlyPlan;
use App\Models\Post;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use App\Services\Media\FakeMediaInspector;
use App\Services\Media\MediaInspector;
use App\Services\Planning\PromptBuilder;
use Database\Seeders\DefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComposeFromMediaTest extends TestCase
{
    use RefreshDatabase;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(DefaultsSeeder::class);
        $this->app->instance(MediaInspector::class, new FakeMediaInspector);

        Storage::disk('local')->put('brands/1/posts/1/source.jpg', 'fakejpeg');

        $this->post = Post::factory()->create([
            'monthly_plan_id' => MonthlyPlan::factory()->create(['year' => 2026, 'month' => 10]),
            'network' => Network::Instagram,
            'format' => PostFormat::Image,
            'status' => PostStatus::Drafted,
            'hook' => 'Analizando material…',
            'copy' => '',
            'hashtags' => [],
            'design_payload' => ['slides' => []],
            'media_source' => MediaSource::Upload,
            'media_mode' => MediaMode::Template,
            'source_path' => 'brands/1/posts/1/source.jpg',
            'source_mime' => 'image/jpeg',
            'source_meta' => ['width' => 800, 'height' => 600, 'duration' => null, 'size' => 8, 'mime' => 'image/jpeg'],
            'operator_note' => 'Foto del taller de robótica',
            'composing_at' => now(),
        ]);
    }

    private function runJob(bool $reuse = false, ?string $comment = null): void
    {
        (new ComposeFromMedia($this->post, $reuse, $comment))->handle(
            app(LlmClient::class),
            app(PromptBuilder::class),
            app(MediaInspector::class),
        );
    }

    public function test_redacta_el_post_en_dos_llamadas_y_registra_cuota(): void
    {
        $pillar = ContentPillar::first();

        $fake = (new FakeLlmClient)
            ->withResponse([
                'description' => 'Estudiantes en un taller con laptops',
                'elements' => ['laptops', 'mesa'],
                'people' => 'estudiantes',
                'visible_text' => '',
                'mood' => 'colaborativo',
                'suggested_angle' => 'aprendizaje práctico',
            ])
            ->withResponse([
                'hook' => 'Manos a la obra en el taller',
                'copy' => 'Así aprenden mejor.',
                'hashtags' => ['#taller', '#robotica'],
                'design_payload' => ['slides' => [['title' => 'Taller', 'body' => 'Práctica']]],
                'content_pillar_id' => $pillar->id,
                'icon' => 'users',
            ]);
        $this->app->instance(LlmClient::class, $fake);

        $this->runJob();

        $post = $this->post->fresh();
        $this->assertSame('Manos a la obra en el taller', $post->hook);
        $this->assertSame('Así aprenden mejor.', $post->copy);
        $this->assertSame(['#taller', '#robotica'], $post->hashtags);
        $this->assertSame($pillar->id, $post->content_pillar_id);
        $this->assertSame('users', $post->icon->value);
        $this->assertSame('Estudiantes en un taller con laptops', $post->media_description['description']);
        $this->assertNull($post->composing_at);
        $this->assertNull($post->compose_error);

        // Una llamada de visión con 1 imagen (foto).
        $this->assertCount(1, $fake->describeCalls);
        $this->assertSame(1, $fake->describeCalls[0]['images']);
        $this->assertSame('media_description', $fake->describeCalls[0]['purpose']);

        $usage = $post->brand->usageFor(2026, 10);
        $this->assertSame(1, (int) $usage->posts_generated);
    }

    public function test_fallo_del_llm_deja_compose_error(): void
    {
        $fake = (new FakeLlmClient)->withException('El modelo de visión no responde');
        $this->app->instance(LlmClient::class, $fake);

        $this->runJob();

        $post = $this->post->fresh();
        $this->assertNull($post->composing_at);
        $this->assertSame('El modelo de visión no responde', $post->compose_error);
    }

    public function test_reuse_description_omite_la_vision_y_cobra_regeneracion(): void
    {
        $pillar = ContentPillar::first();
        $this->post->update(['media_description' => ['description' => 'Ya descrito']]);

        $fake = (new FakeLlmClient)->withResponse([
            'hook' => 'Otro enfoque',
            'copy' => 'Copy nuevo.',
            'hashtags' => [],
            'design_payload' => ['slides' => []],
            'content_pillar_id' => $pillar->id,
            'icon' => 'book',
        ]);
        $this->app->instance(LlmClient::class, $fake);

        $this->runJob(reuse: true, comment: 'Más breve');

        // Solo una llamada: la de redacción; la visión se omite.
        $this->assertCount(0, $fake->describeCalls);
        $this->assertSame(1, LlmLog::where('purpose', 'post_from_media')->count());

        $post = $this->post->fresh();
        $this->assertSame('Otro enfoque', $post->hook);
        $this->assertSame('Ya descrito', $post->media_description['description']);
        $this->assertNull($post->composing_at);

        $usage = $post->brand->usageFor(2026, 10);
        $this->assertSame(1, (int) $usage->regenerations);
        $this->assertSame(0, (int) $usage->posts_generated);
    }

    public function test_pilar_fuera_de_la_marca_no_se_asigna(): void
    {
        $this->post->update(['content_pillar_id' => null]);

        $fake = (new FakeLlmClient)
            ->withResponse(['description' => 'x'])
            ->withResponse([
                'hook' => 'H', 'copy' => 'C', 'hashtags' => [],
                'design_payload' => ['slides' => []],
                'content_pillar_id' => 99999,
                'icon' => 'book',
            ]);
        $this->app->instance(LlmClient::class, $fake);

        $this->runJob();

        $this->assertNull($this->post->fresh()->content_pillar_id);
    }
}
