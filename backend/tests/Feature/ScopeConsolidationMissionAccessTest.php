<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\SystemRole;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Modules\HumanResource\Support\OrgHierarchyAccessService;
use Tests\TestCase;

/**
 * Phase 3B.1 (scope consolidation): OrgHierarchyAccessService::departmentIdsForResponsibility()
 * was extracted so Modules\HumanResource\Support\MissionAccess::queueByResponsibility()
 * no longer re-implements UserAssignment scope-type expansion itself. These
 * tests cover the extracted method directly (MissionAccess itself just
 * forwards to it -- see queueByResponsibility()). Runs against the real (dev)
 * database, wrapped in a transaction that is rolled back afterwards.
 */
class ScopeConsolidationMissionAccessTest extends TestCase
{
    use DatabaseTransactions;

    private const SUPER_ADMIN_USER_ID = 25;
    private const NON_ADMIN_USER_ID = 640;
    private const RESPONSIBILITY_HEAD_ID = 1;
    private const RESPONSIBILITY_MANAGER_ID = 3;

    private array $deptIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->deptIds = Department::query()
            ->where('is_active', 1)
            ->orderBy('id')
            ->limit(3)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->assertGreaterThanOrEqual(3, count($this->deptIds), 'Need at least 3 active departments in the dev DB for this test.');
    }

    private function makeAssignment(int $userId, int $responsibilityId, int $departmentId, string $scopeType): UserAssignment
    {
        return app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $userId,
            'department_id' => $departmentId,
            'responsibility_id' => $responsibilityId,
            'scope_type' => $scopeType,
            'is_primary' => false,
            'is_active' => true,
        ]);
    }

    public function test_system_admin_is_unrestricted(): void
    {
        $admin = User::query()->findOrFail(self::SUPER_ADMIN_USER_ID);

        $ids = app(OrgHierarchyAccessService::class)->departmentIdsForResponsibility($admin, SystemRole::CODE_HEAD);

        $this->assertNull($ids);
    }

    public function test_user_with_no_matching_responsibility_gets_empty_array(): void
    {
        $target = User::query()->findOrFail(self::NON_ADMIN_USER_ID);
        $this->makeAssignment($target->id, self::RESPONSIBILITY_MANAGER_ID, $this->deptIds[0], UserAssignment::SCOPE_SELF_ONLY);

        // Target holds a "manager" assignment but not "head" -- asking for
        // "head" must not leak the manager assignment's departments.
        $ids = app(OrgHierarchyAccessService::class)->departmentIdsForResponsibility($target, SystemRole::CODE_HEAD);

        $this->assertSame([], $ids);
    }

    public function test_self_only_scope_returns_exactly_that_department(): void
    {
        $target = User::query()->findOrFail(self::NON_ADMIN_USER_ID);
        $this->makeAssignment($target->id, self::RESPONSIBILITY_HEAD_ID, $this->deptIds[0], UserAssignment::SCOPE_SELF_ONLY);

        $ids = app(OrgHierarchyAccessService::class)->departmentIdsForResponsibility($target, SystemRole::CODE_HEAD);

        $this->assertSame([$this->deptIds[0]], $ids);
    }

    public function test_all_scope_is_unrestricted_for_that_responsibility(): void
    {
        $target = User::query()->findOrFail(self::NON_ADMIN_USER_ID);
        $this->makeAssignment($target->id, self::RESPONSIBILITY_HEAD_ID, $this->deptIds[0], UserAssignment::SCOPE_ALL);

        $ids = app(OrgHierarchyAccessService::class)->departmentIdsForResponsibility($target, SystemRole::CODE_HEAD);

        $this->assertNull($ids);
    }

    public function test_multiple_assignments_for_the_same_responsibility_are_merged(): void
    {
        $target = User::query()->findOrFail(self::NON_ADMIN_USER_ID);
        $this->makeAssignment($target->id, self::RESPONSIBILITY_HEAD_ID, $this->deptIds[0], UserAssignment::SCOPE_SELF_ONLY);
        $this->makeAssignment($target->id, self::RESPONSIBILITY_HEAD_ID, $this->deptIds[1], UserAssignment::SCOPE_SELF_ONLY);

        $ids = app(OrgHierarchyAccessService::class)->departmentIdsForResponsibility($target, SystemRole::CODE_HEAD);

        sort($ids);
        $expected = [$this->deptIds[0], $this->deptIds[1]];
        sort($expected);
        $this->assertSame($expected, $ids);
    }
}
