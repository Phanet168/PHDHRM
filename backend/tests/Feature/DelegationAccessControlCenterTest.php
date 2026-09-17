<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\WorkflowDefinition;
use Modules\HumanResource\Entities\WorkflowDefinitionStep;
use Modules\HumanResource\Services\DelegationService;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Tests\TestCase;

/**
 * Phase 3D.1: Access Control Center Delegation tab -- HTTP/controller/route
 * layer, plus the Effective Access "temporary_delegations" integration.
 * Runs against the real (dev) database, wrapped in a transaction that is
 * rolled back afterwards.
 */
class DelegationAccessControlCenterTest extends TestCase
{
    use DatabaseTransactions;

    private const SUPER_ADMIN_USER_ID = 25;
    private const NON_ADMIN_USER_ID = 640;
    private const DELEGATEE_USER_ID = 26;
    private const RESPONSIBILITY_HEAD_ID = 1;

    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $unitTypeId = (int) \Illuminate\Support\Facades\DB::table('org_unit_types')->orderBy('id')->value('id');
        $this->dept = Department::create(['department_name' => 'PHPUnit ACC Delegation Dept', 'unit_type_id' => $unitTypeId, 'is_active' => true]);

        $definition = WorkflowDefinition::create([
            'module_key' => 'phpunit_acc_deleg',
            'request_type_key' => 'test_type',
            'name' => 'PHPUnit ACC Delegation Workflow',
            'priority' => 999,
            'is_active' => true,
        ]);
        $definition->steps()->create([
            'step_order' => 1,
            'step_name' => 'PHPUnit Approval',
            'action_type' => 'approve',
            'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY,
            'actor_responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'system_role_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
            'is_final_approval' => true,
            'is_required' => true,
            'can_return' => true,
            'can_reject' => true,
        ]);
    }

    private function superAdmin(): User
    {
        return User::query()->findOrFail(self::SUPER_ADMIN_USER_ID);
    }

    private function delegator(): User
    {
        return User::query()->findOrFail(self::NON_ADMIN_USER_ID);
    }

    private function delegatee(): User
    {
        return User::query()->findOrFail(self::DELEGATEE_USER_ID);
    }

    private function giveNativeAuthority(): void
    {
        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $this->delegator()->id,
            'department_id' => $this->dept->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_ALL,
            'is_primary' => false,
            'is_active' => true,
        ]);
    }

    public function test_unauthorized_user_cannot_view_or_manage_delegations(): void
    {
        $this->actingAs($this->delegatee())->getJson(route('access-control.delegations.index'))->assertForbidden();
        $this->actingAs($this->delegatee())->postJson(route('access-control.delegations.store'), [])->assertForbidden();
    }

    public function test_authorized_admin_can_list_delegation_authorities_for_a_candidate_delegator(): void
    {
        $this->giveNativeAuthority();

        $response = $this->actingAs($this->superAdmin())
            ->getJson(route('access-control.delegations.authorities', $this->delegator()->id))
            ->assertOk();

        $moduleKeys = collect($response->json('data'))->pluck('module_key');
        $this->assertContains('phpunit_acc_deleg', $moduleKeys);
    }

    public function test_boundary_endpoint_marks_units_in_and_out_of_scope(): void
    {
        $this->giveNativeAuthority(); // SCOPE_ALL -> unrestricted

        $response = $this->actingAs($this->superAdmin())
            ->getJson(route('access-control.delegations.boundary', $this->delegator()->id))
            ->assertOk();

        $this->assertTrue($response->json('data.unrestricted'));
    }

    public function test_admin_can_create_and_list_a_delegation(): void
    {
        $this->giveNativeAuthority();

        $response = $this->actingAs($this->superAdmin())->postJson(route('access-control.delegations.store'), [
            'delegator_user_id' => $this->delegator()->id,
            'delegatee_user_id' => $this->delegatee()->id,
            'authority_module_key' => 'phpunit_acc_deleg',
            'scope_type' => 'UNIT',
            'department_ids' => [$this->dept->id],
            'starts_at' => now()->subHour()->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
            'reason' => 'PHPUnit test',
        ])->assertCreated();

        $uuid = $response->json('data.uuid');
        $this->assertNotNull($uuid);

        $this->actingAs($this->superAdmin())
            ->getJson(route('access-control.delegations.index', ['state' => 'active']))
            ->assertOk()
            ->assertJsonFragment(['uuid' => $uuid]);
    }

    public function test_admin_can_revoke_a_delegation(): void
    {
        $this->giveNativeAuthority();
        $delegation = app(DelegationService::class)->create([
            'delegator_user_id' => $this->delegator()->id,
            'delegatee_user_id' => $this->delegatee()->id,
            'authority_module_key' => 'phpunit_acc_deleg',
            'scope_type' => UserAssignment::SCOPE_ALL,
            'scope_department_ids' => [],
            'starts_at' => now()->subHour()->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
        ]);

        $this->actingAs($this->superAdmin())
            ->postJson(route('access-control.delegations.revoke', $delegation->uuid))
            ->assertOk()
            ->assertJsonPath('data.state', 'revoked');
    }

    public function test_store_rejects_self_delegation_with_422(): void
    {
        $this->giveNativeAuthority();

        $this->actingAs($this->superAdmin())->postJson(route('access-control.delegations.store'), [
            'delegator_user_id' => $this->delegator()->id,
            'delegatee_user_id' => $this->delegator()->id,
            'authority_module_key' => 'phpunit_acc_deleg',
            'scope_type' => 'ORGANIZATION',
            'department_ids' => [],
            'starts_at' => now()->subHour()->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
        ])->assertStatus(422);
    }

    public function test_store_rejects_authority_the_delegator_does_not_hold_with_422(): void
    {
        // delegator has no UserAssignment at all for this module's responsibility.
        $this->actingAs($this->superAdmin())->postJson(route('access-control.delegations.store'), [
            'delegator_user_id' => $this->delegator()->id,
            'delegatee_user_id' => $this->delegatee()->id,
            'authority_module_key' => 'phpunit_acc_deleg',
            'scope_type' => 'ORGANIZATION',
            'department_ids' => [],
            'starts_at' => now()->subHour()->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
        ])->assertStatus(422);
    }

    public function test_store_rejects_scope_escalation_with_422(): void
    {
        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $this->delegator()->id,
            'department_id' => $this->dept->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY, // narrow, not unrestricted
            'is_primary' => false,
            'is_active' => true,
        ]);

        $this->actingAs($this->superAdmin())->postJson(route('access-control.delegations.store'), [
            'delegator_user_id' => $this->delegator()->id,
            'delegatee_user_id' => $this->delegatee()->id,
            'authority_module_key' => 'phpunit_acc_deleg',
            'scope_type' => 'ORGANIZATION',
            'department_ids' => [],
            'starts_at' => now()->subHour()->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
        ])->assertStatus(422);
    }

    public function test_effective_access_reports_active_incoming_delegation(): void
    {
        $this->giveNativeAuthority();
        app(DelegationService::class)->create([
            'delegator_user_id' => $this->delegator()->id,
            'delegatee_user_id' => $this->delegatee()->id,
            'authority_module_key' => 'phpunit_acc_deleg',
            'scope_type' => UserAssignment::SCOPE_ALL,
            'scope_department_ids' => [],
            'starts_at' => now()->subHour()->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
        ]);

        $effectiveAccess = app(AccessControlService::class)->effectiveAccess($this->delegatee());

        $this->assertArrayHasKey('temporary_delegations', $effectiveAccess);
        $this->assertCount(1, $effectiveAccess['temporary_delegations']);
        $this->assertSame('phpunit_acc_deleg', $effectiveAccess['temporary_delegations'][0]['authority_module_key']);
        $this->assertSame($this->delegator()->id, $effectiveAccess['temporary_delegations'][0]['from_user_id']);

        // Must never be mixed into the WHAT (roles/permissions) modules breakdown.
        $this->assertArrayNotHasKey('temporary_delegations', $effectiveAccess['modules']);
    }
}
