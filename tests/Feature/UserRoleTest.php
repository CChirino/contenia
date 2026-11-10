<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_gestor_role_persists_and_enables_brand_creation(): void
    {
        $user = User::factory()->create(['role' => UserRole::Manager]);

        $fresh = $user->fresh();
        $this->assertSame(UserRole::Manager, $fresh->role);
        $this->assertSame('gestor', $fresh->role->value);
        $this->assertTrue($fresh->isManager());
        $this->assertFalse($fresh->isAdmin());
        $this->assertFalse($fresh->isOperator());
        $this->assertTrue($fresh->canCreateBrands());
    }

    public function test_operator_cannot_create_brands(): void
    {
        $user = User::factory()->create(['role' => UserRole::Operator]);

        $this->assertFalse($user->fresh()->canCreateBrands());
        $this->assertTrue($user->fresh()->isOperator());
    }
}
