<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Modules\HumanResource\Entities\Delegation;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\WorkflowDefinition;
use Modules\HumanResource\Entities\WorkflowDefinitionStep;
use Modules\HumanResource\Services\DelegationService;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Tests\TestCase;

/**
 * Phase 3D.1, section 34: DelegationService validation rules. Runs against
 * the real (dev) database, wrapped in a transaction that is rolled back
 * afterwards.
 */
class DelegationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const RESPONSIBILITY_HEAD_ID = 1;
    private const NON_ADMIN_A_ID = 640; // "Director" in these tests
    private const NON_ADMIN_B_ID = 26; // "Unit-scoped user" / delegatee

    private Department $phd;
    private Department $odA;
    private Department $hcA1;
    private Department $hcA2;
    private Department $odB;
    private Department $hcB1;
    private WorkflowDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();

        $unitTypeId = (int) \Illuminate\Support\Facades\DB::table('org_unit_types')->orderBy('id')->value('id');
        $this->phd = Department::create(['department_name' => 'PHPUnit PHD', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
        $this->odA = Department::create(['department_name' => 'PHPUnit OD A', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->phd->id, 'is_active' => true]);
        $this->hcA1 = Department::create(['department_name' => 'PHPUnit HC A1', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->odA->id, 'is_active' => true]);
        $this->hcA2 = Department::create(['department_name' => 'PHPUnit HC A2', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->odA->id, 'is_active' => true]);
        $this->odB = Department::create(['department_name' => 'PHPUnit OD B', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->phd->id, 'is_active' => true]);
        $this->hcB1 = Department::create(['department_name' => 'PHPUnit HC B1', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->odB->id, 'is_active' => true]);

        $this->definition = WorkflowDefinition::create([
            'module_key' => 'phpunit_deleg_svc',
            'request_type_key' => 'test_type',
            'name' => 'PHPUnit Delegation Service Test Workflow',
            'priority' => 999,
            'is_active' => true,
        ]);
        $this->definition->steps()->create([
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

    private function director(): User
    {
        return User::query()->findOrFail(self::NON_ADMIN_A_ID);
    }

    private function unitScopedUser(): User
    {
        return User::query()->findOrFail(self::NON_ADMIN_B_ID);
    }

    private function giveNativeAuthority(User $user, Department $department, string $scopeType): void
    {
        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => $scopeType,
            'is_primary' => false,
            'is_active' => true,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'delegator_user_id' => $this->director()->id,
            'delegatee_user_id' => $this->unitScopedUser()->id,
            'authority_module_key' => 'phpunit_deleg_svc',
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'scope_department_ids' => [$this->odA->id],
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDays(2)->toDateTimeString(),
        ], $overrides);
    }

    // ---------------- Creation ----------------

    public function test_valid_creation(): void
    {
        $this->giveNativeAuthority($this->director(), $this->phd, UserAssignment::SCOPE_ALL);

        $delegation = app(DelegationService::class)->create($this->validPayload());

        $this->assertNotNull($delegation->id);
        $this->assertSame($this->director()->id, $delegation->delegator_user_id);
        $this->assertSame($this->unitScopedUser()->id, $delegation->delegatee_user_id);
        $this->assertSame('phpunit_deleg_svc', $delegation->authority_module_key);
        $this->assertSame(Delegation::STATUS_ACTIVE, $delegation->status);
    }

    public function test_self_delegation_is_rejected(): void
    {
        $this->giveNativeAuthority($this->director(), $this->phd, UserAssignment::SCOPE_ALL);

        $this->expectException(ValidationException::class);
        app(DelegationService::class)->create($this->validPayload([
            'delegatee_user_id' => $this->director()->id,
        ]));
    }

    public function test_nonexistent_user_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(DelegationService::class)->create($this->validPayload(['delegatee_user_id' => 999999999]));
    }

    public function test_invalid_period_is_rejected(): void
    {
        $this->giveNativeAuthority($this->director(), $this->phd, UserAssignment::SCOPE_ALL);

        $this->expectException(ValidationException::class);
        app(DelegationService::class)->create($this->validPayload([
            'starts_at' => now()->addDays(2)->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
        ]));
    }

    public function test_authority_not_owned_by_delegator_is_rejected(): void
    {
        // unitScopedUser has NO UserAssignment for the head responsibility at all.
        $this->expectException(ValidationException::class);
        app(DelegationService::class)->create($this->validPayload([
            'delegator_user_id' => $this->unitScopedUser()->id,
            'delegatee_user_id' => $this->director()->id,
        ]));
    }

    public function test_unrelated_authority_not_listed_as_delegatable(): void
    {
        $this->giveNativeAuthority($this->director(), $this->phd, UserAssignment::SCOPE_ALL);

        $keys = collect(app(DelegationService::class)->delegatableAuthoritiesFor($this->director()))->pluck('module_key');

        $this->assertContains('phpunit_deleg_svc', $keys);
        $this->assertNotContains('some_module_the_director_never_qualifies_for', $keys);
    }

    // ---------------- Scope bounding ----------------

    public function test_scope_escalation_to_organization_is_rejected(): void
    {
        $this->giveNativeAuthority($this->unitScopedUser(), $this->odA, UserAssignment::SCOPE_SELF_AND_CHILDREN);

        $this->expectException(ValidationException::class);
        app(DelegationService::class)->create($this->validPayload([
            'delegator_user_id' => $this->unitScopedUser()->id,
            'delegatee_user_id' => $this->director()->id,
            'scope_type' => UserAssignment::SCOPE_ALL,
            'scope_department_ids' => [],
        ]));
    }

    public function test_selected_units_bounded_correctly(): void
    {
        // unitScopedUser's boundary is OD A's branch: OD A + HC A1 + HC A2.
        $this->giveNativeAuthority($this->unitScopedUser(), $this->odA, UserAssignment::SCOPE_SELF_AND_CHILDREN);

        // Both selected units inside the branch -> allowed.
        $delegation = app(DelegationService::class)->create($this->validPayload([
            'delegator_user_id' => $this->unitScopedUser()->id,
            'delegatee_user_id' => $this->director()->id,
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'scope_department_ids' => [$this->hcA1->id, $this->hcA2->id],
        ]));
        $this->assertEqualsCanonicalizing([$this->hcA1->id, $this->hcA2->id], $delegation->scopeDepartmentIds());

        // One selected unit (HC B1) outside the branch -> rejected.
        $this->expectException(ValidationException::class);
        app(DelegationService::class)->create($this->validPayload([
            'delegator_user_id' => $this->unitScopedUser()->id,
            'delegatee_user_id' => $this->director()->id,
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'scope_department_ids' => [$this->hcA1->id, $this->hcB1->id],
        ]));
    }

    public function test_unrestricted_delegator_may_delegate_any_units(): void
    {
        $this->giveNativeAuthority($this->director(), $this->phd, UserAssignment::SCOPE_ALL);

        $delegation = app(DelegationService::class)->create($this->validPayload([
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'scope_department_ids' => [$this->hcA1->id, $this->hcB1->id],
        ]));

        $this->assertEqualsCanonicalizing([$this->hcA1->id, $this->hcB1->id], $delegation->scopeDepartmentIds());
    }

    // ---------------- No re-delegation of borrowed authority ----------------

    public function test_delegated_authority_is_not_re_delegatable(): void
    {
        $this->giveNativeAuthority($this->director(), $this->phd, UserAssignment::SCOPE_ALL);

        // Director -> unitScopedUser (who has no native authority at all).
        app(DelegationService::class)->create($this->validPayload());

        // unitScopedUser now holds this authority ONLY via the delegation
        // above. They must not be able to delegate it onward.
        $keys = collect(app(DelegationService::class)->delegatableAuthoritiesFor($this->unitScopedUser()))->pluck('module_key');
        $this->assertNotContains('phpunit_deleg_svc', $keys);

        $this->expectException(ValidationException::class);
        app(DelegationService::class)->create($this->validPayload([
            'delegator_user_id' => $this->unitScopedUser()->id,
            'delegatee_user_id' => $this->director()->id,
        ]));
    }

    // ---------------- Time / revocation ----------------

    public function test_revoked_delegation_is_not_effective(): void
    {
        $this->giveNativeAuthority($this->director(), $this->phd, UserAssignment::SCOPE_ALL);

        $delegation = app(DelegationService::class)->create($this->validPayload([
            'starts_at' => now()->subHour()->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
        ]));
        $this->assertTrue($delegation->isEffectiveAt(now()));

        $revoked = app(DelegationService::class)->revoke($delegation, $this->director()->id, 'no longer needed');

        $this->assertSame(Delegation::STATUS_REVOKED, $revoked->status);
        $this->assertFalse($revoked->isEffectiveAt(now()));
        $this->assertSame('revoked', $revoked->computedState());
        $this->assertSame((int) $this->director()->id, (int) $revoked->revoked_by);
        $this->assertNotNull($revoked->revoked_at);
    }

    public function test_expired_delegation_is_not_effective(): void
    {
        $this->giveNativeAuthority($this->director(), $this->phd, UserAssignment::SCOPE_ALL);

        $delegation = Delegation::create([
            'delegator_user_id' => $this->director()->id,
            'delegatee_user_id' => $this->unitScopedUser()->id,
            'authority_type' => Delegation::AUTHORITY_TYPE_WORKFLOW_APPROVAL,
            'authority_module_key' => 'phpunit_deleg_svc',
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'scope_department_ids' => [$this->odA->id],
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'status' => Delegation::STATUS_ACTIVE,
        ]);

        $this->assertFalse($delegation->isEffectiveAt(now()));
        $this->assertSame('expired', $delegation->computedState());
    }

    public function test_future_delegation_is_not_effective_until_start(): void
    {
        $this->giveNativeAuthority($this->director(), $this->phd, UserAssignment::SCOPE_ALL);

        $delegation = app(DelegationService::class)->create($this->validPayload([
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDays(2)->toDateTimeString(),
        ]));

        $this->assertFalse($delegation->isEffectiveAt(now()));
        $this->assertSame('upcoming', $delegation->computedState());
        $this->assertTrue($delegation->isEffectiveAt(now()->addDay()->addHour()));
    }
}
