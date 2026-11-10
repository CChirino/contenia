<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_elimina_y_restaura_conservando_marcas(): void
    {
        $this->actingAsAdminFor();
        $brand = Brand::factory()->create();
        $operator = User::factory()->create(['role' => UserRole::Operator]);
        $operator->brands()->attach($brand);

        $this->delete(route('users.destroy', $operator))->assertRedirect(route('users.index'));
        $this->assertSoftDeleted($operator);

        $this->get(route('users.index'))->assertInertia(fn ($page) => $page
            ->where('deletedUsers.0.id', $operator->id)
            ->where('users', fn ($users) => collect($users)->pluck('id')->doesntContain($operator->id))
        );

        $this->post(route('users.restore', $operator->id))->assertRedirect(route('users.index'));
        $this->assertNotSoftDeleted($operator);
        $this->assertTrue($operator->fresh()->brands()->whereKey($brand->id)->exists());
    }

    public function test_usuario_eliminado_no_puede_iniciar_sesion_ni_seguir_en_sesion(): void
    {
        $user = User::factory()->create(['email' => 'borrado@test.com', 'password' => 'password']);
        $this->actingAs($user);
        $user->delete();

        // La sesión activa cae: el guard ya no encuentra al usuario.
        $this->app['auth']->forgetGuards();
        $this->get('/dashboard')->assertRedirect(route('login'));

        $this->post('/login', ['email' => 'borrado@test.com', 'password' => 'password']);
        $this->assertGuest();
    }

    public function test_no_puede_eliminarse_a_si_mismo_ni_al_ultimo_admin(): void
    {
        $admin = $this->actingAsAdminFor();
        User::where('role', UserRole::Admin)->whereKeyNot($admin->id)->delete();

        $this->delete(route('users.destroy', $admin))->assertSessionHasErrors('user');
        $this->assertNotSoftDeleted($admin);

        $otherAdmin = User::factory()->create(['role' => UserRole::Admin]);
        $otherAdmin->delete();
        // Con un solo admin activo, eliminar a otro admin (ya borrado) no aplica;
        // un segundo admin activo sí se puede eliminar.
        $second = User::factory()->create(['role' => UserRole::Admin]);
        $this->delete(route('users.destroy', $second))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($second);
    }

    public function test_gestor_y_operador_no_pueden_eliminar_usuarios(): void
    {
        $target = User::factory()->create(['role' => UserRole::Operator]);

        $this->actingAsManagerFor();
        $this->delete(route('users.destroy', $target))->assertForbidden();

        $this->actingAsOperatorFor();
        $this->delete(route('users.destroy', $target))->assertForbidden();

        $this->assertNotSoftDeleted($target);
    }

    public function test_crear_con_correo_de_usuario_eliminado_pide_restaurar(): void
    {
        $this->actingAsAdminFor();
        User::factory()->create(['email' => 'repetido@test.com'])->delete();

        $this->post(route('users.store'), [
            'name' => 'Otro',
            'email' => 'repetido@test.com',
            'password' => 'password123',
            'role' => 'operator',
        ])->assertSessionHasErrors(['email' => 'Ya existe un usuario eliminado con ese correo: restáuralo desde "Usuarios eliminados".']);
    }

    public function test_url_del_logo_cambia_al_subir_uno_nuevo(): void
    {
        Storage::fake('local');
        $this->actingAsAdminFor();
        $brand = Brand::factory()->create(['updated_at' => now()->subDay()]);
        $before = $brand->logoUrl();

        $this->put(route('brands.update', $brand), [
            'name' => $brand->name,
            'plan_id' => $brand->plan_id,
            'palette' => $brand->palette,
            'requires_client_approval' => false,
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])->assertSessionHasNoErrors();

        $brand->refresh();
        $this->assertSame("brands/{$brand->id}/logo.png", $brand->logo_path);
        $this->assertNotSame($before, $brand->logoUrl());
        $this->assertStringContainsString('?v=', $brand->logoUrl());
    }
}
