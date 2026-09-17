<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\HumanResource\Entities\WorkflowDefinition;
use Modules\HumanResource\Entities\WorkflowDefinitionStep;
use Tests\TestCase;

/**
 * Feature tests for the Approval Authority tab added to the Phase 3A Access
 * Control Center. Runs against the project's real (dev) database, wrapped in
 * a database transaction that is rolled back afterwards. Every
 * WorkflowDefinition/step used here is a throwaway row created by the test
 * itself -- the real Leave/Notice/Mission/Correspondence/Attendance
 * definitions are never read or mutated.
 */
class AccessControlApprovalAuthorityTest extends TestCase
{
    use DatabaseTransactions;

    private const SUPER_ADMIN_USER_ID = 25;
    private const NON_ADMIN_USER_ID = 640;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    private function superAdmin(): User
    {
        return User::query()->findOrFail(self::SUPER_ADMIN_USER_ID);
    }

    private function nonAdmin(): User
    {
        return User::query()->findOrFail(self::NON_ADMIN_USER_ID);
    }

    private function makeDefinition(array $stepsOverrides = []): WorkflowDefinition
    {
        $definition = WorkflowDefinition::create([
            'module_key' => 'phpunit_test',
            'request_type_key' => 'phpunit_test_type',
            'name' => 'PHPUnit Test Workflow',
            'priority' => 999,
            'is_active' => true,
        ]);

        $definition->steps()->create(array_merge([
            'step_order' => 1,
            'step_name' => 'PHPUnit Step',
            'action_type' => 'approve',
            'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY,
            'actor_responsibility_id' => 1,
            'system_role_id' => 1,
            'scope_type' => 'self_and_children',
            'is_final_approval' => true,
            'is_required' => true,
            'can_return' => true,
            'can_reject' => true,
        ], $stepsOverrides));

        return $definition->fresh('steps');
    }

    // ---------------- Authorization ----------------

    public function test_unauthorized_user_cannot_view_or_manage_approvals(): void
    {
        $definition = $this->makeDefinition();

        $this->actingAs($this->nonAdmin())->getJson(route('access-control.approvals.index'))->assertForbidden();
        $this->actingAs($this->nonAdmin())->getJson(route('access-control.approvals.show', $definition->id))->assertForbidden();
        $this->actingAs($this->nonAdmin())->putJson(route('access-control.approvals.update', $definition->id), [])->assertForbidden();
        $this->actingAs($this->nonAdmin())->getJson(route('access-control.users.approval-authority', $this->nonAdmin()->id))->assertForbidden();
    }

    // ---------------- Listing / viewing ----------------

    public function test_authorized_admin_can_list_and_view_a_definition(): void
    {
        $definition = $this->makeDefinition();

        $this->actingAs($this->superAdmin())
            ->getJson(route('access-control.approvals.index'))
            ->assertOk()
            ->assertJsonFragment(['id' => $definition->id, 'module_key' => 'phpunit_test']);

        $this->actingAs($this->superAdmin())
            ->getJson(route('access-control.approvals.show', $definition->id))
            ->assertOk()
            ->assertJsonPath('data.steps.0.actor_type', WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY)
            ->assertJsonPath('data.steps.0.is_final_approval', true);
    }

    public function test_approval_options_returns_reference_data(): void
    {
        $this->actingAs($this->superAdmin())
            ->getJson(route('access-control.approvals.options'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['action_type_options', 'actor_type_options', 'scope_type_options', 'positions', 'responsibilities', 'spatie_roles', 'users'],
            ]);
    }

    // ---------------- Editing ----------------

    public function test_admin_can_update_definition_fields_and_steps(): void
    {
        $definition = $this->makeDefinition();

        $response = $this->actingAs($this->superAdmin())
            ->putJson(route('access-control.approvals.update', $definition->id), [
                'name' => 'Updated Workflow Name',
                'priority' => 50,
                'is_active' => false,
                'steps' => [
                    [
                        'step_order' => 1,
                        'step_name' => 'Reviewer step',
                        'action_type' => 'review',
                        'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_POSITION,
                        'actor_position_id' => 1,
                        'scope_type' => 'self_and_children',
                        'is_final_approval' => false,
                        'is_required' => true,
                        'can_return' => true,
                        'can_reject' => true,
                    ],
                    [
                        'step_order' => 2,
                        'step_name' => 'Final approval step',
                        'action_type' => 'approve',
                        'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_SPATIE_ROLE,
                        'actor_role_id' => 1,
                        'scope_type' => 'all',
                        'is_final_approval' => true,
                        'is_required' => true,
                        'can_return' => false,
                        'can_reject' => true,
                    ],
                ],
            ])
            ->assertOk();

        $response->assertJsonPath('data.name', 'Updated Workflow Name');
        $response->assertJsonPath('data.is_active', false);
        $response->assertJsonCount(2, 'data.steps');

        $definition->refresh();
        $this->assertSame('Updated Workflow Name', $definition->name);
        $this->assertFalse((bool) $definition->is_active);
        $this->assertCount(2, $definition->steps()->get());
    }

    public function test_duplicate_step_order_is_rejected(): void
    {
        $definition = $this->makeDefinition();

        $this->actingAs($this->superAdmin())
            ->putJson(route('access-control.approvals.update', $definition->id), [
                'name' => 'X', 'priority' => 1, 'is_active' => true,
                'steps' => [
                    ['step_order' => 1, 'step_name' => 'A', 'action_type' => 'approve', 'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY, 'actor_responsibility_id' => 1, 'scope_type' => 'self_and_children', 'is_final_approval' => true, 'is_required' => true, 'can_return' => true, 'can_reject' => true],
                    ['step_order' => 1, 'step_name' => 'B', 'action_type' => 'approve', 'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY, 'actor_responsibility_id' => 1, 'scope_type' => 'self_and_children', 'is_final_approval' => true, 'is_required' => true, 'can_return' => true, 'can_reject' => true],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_missing_final_approval_step_is_rejected(): void
    {
        $definition = $this->makeDefinition();

        $this->actingAs($this->superAdmin())
            ->putJson(route('access-control.approvals.update', $definition->id), [
                'name' => 'X', 'priority' => 1, 'is_active' => true,
                'steps' => [
                    ['step_order' => 1, 'step_name' => 'A', 'action_type' => 'review', 'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY, 'actor_responsibility_id' => 1, 'scope_type' => 'self_and_children', 'is_final_approval' => false, 'is_required' => true, 'can_return' => true, 'can_reject' => true],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_responsibility_actor_without_responsibility_id_is_rejected(): void
    {
        $definition = $this->makeDefinition();

        $this->actingAs($this->superAdmin())
            ->putJson(route('access-control.approvals.update', $definition->id), [
                'name' => 'X', 'priority' => 1, 'is_active' => true,
                'steps' => [
                    ['step_order' => 1, 'step_name' => 'A', 'action_type' => 'approve', 'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY, 'scope_type' => 'self_and_children', 'is_final_approval' => true, 'is_required' => true, 'can_return' => true, 'can_reject' => true],
                ],
            ])
            ->assertStatus(422);
    }

    // ---------------- Per-user approval authority ----------------

    public function test_user_approval_authority_reflects_specific_user_actor(): void
    {
        $nonAdmin = $this->nonAdmin();
        $superAdmin = $this->superAdmin();

        $definition = $this->makeDefinition();
        // Second step: only the non-admin fixture user is the named actor.
        $definition->steps()->create([
            'step_order' => 2,
            'step_name' => 'Named actor step',
            'action_type' => 'approve',
            'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER,
            'actor_user_id' => $nonAdmin->id,
            'scope_type' => 'self_and_children',
            'is_final_approval' => true,
            'is_required' => true,
            'can_return' => true,
            'can_reject' => true,
        ]);

        $response = $this->actingAs($superAdmin)
            ->getJson(route('access-control.users.approval-authority', $nonAdmin->id))
            ->assertOk();

        $rows = collect($response->json('data'));
        $namedActorStep = $rows->firstWhere('step_name', 'Named actor step');
        $this->assertNotNull($namedActorStep);
        $this->assertTrue($namedActorStep['can_act']);

        // A step whose named actor is someone else entirely must NOT show as
        // "can_act" for the non-admin target user (isSuperAdmin() is checked
        // against the TARGET user here, so this is a real, non-bypassed check).
        $definition->steps()->create([
            'step_order' => 3,
            'step_name' => 'Someone elses step',
            'action_type' => 'approve',
            'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER,
            'actor_user_id' => $superAdmin->id,
            'scope_type' => 'self_and_children',
            'is_final_approval' => true,
            'is_required' => true,
            'can_return' => true,
            'can_reject' => true,
        ]);

        $rowsAgain = collect($this->actingAs($superAdmin)
            ->getJson(route('access-control.users.approval-authority', $nonAdmin->id))
            ->json('data'));
        $othersStep = $rowsAgain->firstWhere('step_name', 'Someone elses step');
        $this->assertNotNull($othersStep);
        $this->assertFalse($othersStep['can_act']);
    }
}
