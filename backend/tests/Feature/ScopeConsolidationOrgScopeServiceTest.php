<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\UserOrgRole;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Modules\HumanResource\Support\OrgScopeService;
use Tests\TestCase;

/**
 * Phase 3B.1 (scope consolidation): OrgScopeService::userDepartment() (shared
 * by Pharmaceutical/Correspondence/ManualAttendance) must resolve through the
 * canonical UserAssignment table (via OrgHierarchyAccessService), not the
 * legacy user_org_roles mirror -- so it stays correct even when a legacy row
 * has drifted from its canonical counterpart. Runs against the real (dev)
 * database, wrapped in a transaction that is rolled back afterwards.
 */
class ScopeConsolidationOrgScopeServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const NON_ADMIN_USER_ID = 640;
    private const RESPONSIBILITY_HEAD_ID = 1;

    private array $deptIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->deptIds = Department::query()
            ->where('is_active', 1)
            ->orderBy('id')
            ->limit(2)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->assertGreaterThanOrEqual(2, count($this->deptIds), 'Need at least 2 active departments in the dev DB for this test.');
    }

    private function target(): User
    {
        return User::query()->findOrFail(self::NON_ADMIN_USER_ID);
    }

    public function test_user_department_resolves_via_canonical_assignment_for_the_normal_case(): void
    {
        $target = $this->target();
        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $target->id,
            'department_id' => $this->deptIds[0],
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $dept = app(OrgScopeService::class)->userDepartment($target);

        $this->assertNotNull($dept);
        $this->assertSame($this->deptIds[0], $dept->id);
    }

    public function test_user_department_ignores_a_stale_orphaned_legacy_row_in_favor_of_the_canonical_assignment(): void
    {
        $target = $this->target();

        // A stale legacy row with NO canonical UserAssignment counterpart
        // (simulates historical drift / a row created before the
        // UserAssignment/GovernanceAssignmentService sync existed). Created
        // with a lower id than the canonical row below, so the OLD
        // "first active user_org_roles row" logic would have picked this one.
        $staleLegacyRow = UserOrgRole::query()->withoutGlobalScope('sortByLatest')->create([
            'user_id' => $target->id,
            'department_id' => $this->deptIds[1],
            'org_role' => 'head',
            'system_role_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'is_active' => true,
        ]);
        $this->assertNull($staleLegacyRow->user_assignment_id, 'Sanity check: this legacy row is a genuine orphan.');

        // The real, current, canonical assignment.
        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $target->id,
            'department_id' => $this->deptIds[0],
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $dept = app(OrgScopeService::class)->userDepartment($target);

        $this->assertNotNull($dept);
        $this->assertSame(
            $this->deptIds[0],
            $dept->id,
            'userDepartment() must prefer the canonical UserAssignment-backed department over a stale orphaned legacy row.'
        );
    }
}
