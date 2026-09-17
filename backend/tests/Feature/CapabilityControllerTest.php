<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsAccessControlDatabase;
use Tests\TestCase;

/**
 * HTTP-level tests for the new, additive GET /v1/me/capabilities endpoint.
 * Does not touch /auth/login or /auth/profile.
 */
class CapabilityControllerTest extends TestCase
{
    use BuildsAccessControlDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAccessControlDatabase();
    }

    public function test_capabilities_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/me/capabilities')->assertStatus(401);
    }

    public function test_capabilities_endpoint_returns_roles_permissions_and_modules_for_authenticated_user(): void
    {
        $user = $this->makeAccessControlUser();
        $role = $this->makeAccessControlRole('HR Officer');
        $perm = $this->makeAccessControlPermission('read_employee');
        DB::table('role_has_permissions')->insert(['permission_id' => $perm, 'role_id' => $role]);
        DB::table('model_has_roles')->insert(['role_id' => $role, 'model_type' => User::class, 'model_id' => $user->id]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/me/capabilities')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.super_admin', false)
            ->assertJsonPath('data.roles.0', 'HR Officer')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJson(fn ($json) => $json
                ->has('data.permissions')
                ->has('data.assignments')
                ->where('data.modules.human_resource.view', true)
                ->etc()
            );
    }

    public function test_capabilities_endpoint_reflects_super_admin(): void
    {
        $user = $this->makeAccessControlUser(['user_type_id' => 1]);
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/me/capabilities')
            ->assertOk()
            ->assertJsonPath('data.super_admin', true);
    }
}
