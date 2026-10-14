<?php

namespace Tests\Feature;

use App\Enums\BrandStatus;
use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\BrandProfile;
use App\Models\CalendarEvent;
use App\Models\NetworkStrategy;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandAdminTest extends TestCase
{
    use RefreshDatabase;

    private function brandPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Marca Nueva',
            'website_url' => 'https://ejemplo.com',
            'plan_id' => Plan::firstOrFail()->id,
            'palette' => [
                'dark' => '#111111',
                'light' => '#EEEEEE',
                'accent' => '#FFAA00',
                'secondary' => '#00AAFF',
            ],
            'requires_client_approval' => false,
        ], $overrides);
    }

    public function test_admin_lists_brands_and_users(): void
    {
        $this->actingAsAdminFor();

        $this->get('/brands')->assertOk();
        $this->get('/users')->assertOk();
    }

    public function test_operator_cannot_access_admin_routes(): void
    {
        $this->actingAsOperatorFor();

        $this->get('/brands')->assertForbidden();
        $this->get('/users')->assertForbidden();
        $this->post('/brands', $this->brandPayload())->assertForbidden();
        $this->put('/brands/'.Brand::first()->id, $this->brandPayload())->assertForbidden();
        $this->post('/brands/'.Brand::first()->id.'/toggle')->assertForbidden();
        $this->post('/users', [
            'name' => 'Op', 'email' => 'op@x.com', 'password' => 'secret123', 'role' => 'operator',
        ])->assertForbidden();
    }

    public function test_admin_creates_brand_and_seeds_defaults_without_duplicating_holidays(): void
    {
        CalendarEvent::factory()->count(3)->create(['brand_id' => null]);
        $globalCount = CalendarEvent::whereNull('brand_id')->count();

        $this->actingAsAdminFor();

        $this->post('/brands', $this->brandPayload())
            ->assertRedirect('/brands');

        $brand = Brand::where('slug', 'marca-nueva')->firstOrFail();

        $this->assertSame(BrandStatus::Active, $brand->status);
        $this->assertEquals('#111111', $brand->palette['dark']);
        $this->assertNotNull($brand->plan_id);

        // Defaults por marca: perfil + estrategias de las 3 redes.
        $this->assertNotNull(BrandProfile::withoutGlobalScopes()->where('brand_id', $brand->id)->first());
        $this->assertCount(3, NetworkStrategy::withoutGlobalScopes()->where('brand_id', $brand->id)->get());

        // Los feriados globales no se duplican.
        $this->assertSame($globalCount, CalendarEvent::whereNull('brand_id')->count());
        $this->assertSame(0, CalendarEvent::where('brand_id', $brand->id)->count());
    }

    public function test_brand_slug_is_unique(): void
    {
        $this->actingAsAdminFor();

        $this->post('/brands', $this->brandPayload(['name' => 'Saberé']));

        $this->assertDatabaseHas('brands', ['name' => 'Saberé']);
        $this->assertNotSame('sabere', Brand::where('name', 'Saberé')->orderByDesc('id')->first()->slug);
    }

    public function test_admin_uploads_logo_to_brand_path(): void
    {
        Storage::fake('local');

        $this->actingAsAdminFor();

        $this->post('/brands', $this->brandPayload([
            'logo' => UploadedFile::fake()->create('logo.svg', 100, 'image/svg+xml'),
        ]));

        $brand = Brand::where('slug', 'marca-nueva')->firstOrFail();

        $this->assertSame("brands/{$brand->id}/logo.svg", $brand->logo_path);
        Storage::disk('local')->assertExists("brands/{$brand->id}/logo.svg");
    }

    public function test_admin_edits_brand_and_toggles_status(): void
    {
        $brand = Brand::factory()->create();
        $admin = $this->actingAsAdminFor();

        $this->put("/brands/{$brand->id}", $this->brandPayload(['name' => 'Renombrada']))
            ->assertRedirect('/brands');
        $this->assertSame('Renombrada', $brand->fresh()->name);

        $this->post("/brands/{$brand->id}/toggle")->assertRedirect('/brands');
        $this->assertSame(BrandStatus::Paused, $brand->fresh()->status);

        $this->post("/brands/{$brand->id}/toggle");
        $this->assertSame(BrandStatus::Active, $brand->fresh()->status);
    }

    public function test_admin_creates_operator_and_assigns_brands(): void
    {
        $brand = Brand::factory()->create();

        $this->actingAsAdminFor();

        $this->post('/users', [
            'name' => 'Operadora',
            'email' => 'op@ejemplo.com',
            'password' => 'temporal123',
            'role' => 'operator',
            'brand_ids' => [$brand->id],
        ])->assertRedirect('/users');

        $user = User::where('email', 'op@ejemplo.com')->firstOrFail();
        $this->assertSame(UserRole::Operator, $user->role);
        $this->assertTrue($user->brands->contains($brand));
    }

    public function test_admin_updates_user_role_and_brand_assignment(): void
    {
        $brandA = Brand::factory()->create();
        $brandB = Brand::factory()->create();
        $user = User::factory()->create(['role' => UserRole::Operator]);
        $user->brands()->sync([$brandA->id]);

        $this->actingAsAdminFor();

        $this->put("/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'admin',
            'brand_ids' => [$brandB->id],
        ])->assertRedirect('/users');

        $user->refresh();
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue($user->brands->contains($brandB));
        $this->assertFalse($user->brands->contains($brandA));
    }

    public function test_brand_logo_route_serves_uploaded_logo_and_fallback(): void
    {
        Storage::fake('local');

        // Saberé sin logo propio → SVG del repo.
        $sabere = Brand::firstOrFail();
        $this->get("/b/{$sabere->slug}/logo")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');

        // Otra marca sin logo → SVG genérico con inicial y acento de marca.
        $brand = Brand::factory()->create([
            'name' => 'Zapatería',
            'logo_path' => null,
            'palette' => [
                'dark' => '#2B1D3A',
                'light' => '#FFF8F0',
                'accent' => '#F2A541',
                'secondary' => '#3E8E7E',
            ],
        ]);

        $response = $this->get("/b/{$brand->slug}/logo");
        $response->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('Cache-Control', 'max-age=3600, public');

        $svg = $response->getContent();
        $this->assertStringContainsString('Z', $svg);
        $this->assertStringContainsString('#F2A541', $svg);
        $this->assertStringContainsString('#2B1D3A', $svg);
        $this->assertStringNotContainsString('Saberé', $svg);

        // Con logo propio → archivo de la marca.
        Storage::disk('local')->put("brands/{$brand->id}/logo.png", 'png-bytes');
        $brand->update(['logo_path' => "brands/{$brand->id}/logo.png"]);

        $this->get("/b/{$brand->slug}/logo")->assertOk();
    }
}
