<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\UserOrgRole;
use Modules\HumanResource\Support\OrgScopeService;
use Tests\TestCase;

/**
 * Phase 3B.2, step 6-7: regression tests written BEFORE refactoring
 * OrgScopeService::scopedDepartmentIds() to delegate to
 * OrgHierarchyAccessService::expandScopeBranchIds() (they were confirmed by
 * direct code reading to be byte-for-byte equivalent -- same 4 match arms,
 * same underlying siblingSameTypeIds()/branchIdsIncludingSelf() calls). These
 * tests capture scopedDepartmentIds()'s CURRENT behavior across all four
 * scope types using an isolated department fixture, and must still pass
 * unchanged after the refactor. Runs against the real (dev) database,
 * wrapped in a transaction that is rolled back afterwards.
 */
class ScopeConsolidationOrgScopeServiceDedupTest extends TestCase
{
    use DatabaseTransactions;

    private Department $grandParent;
    private Department $unitA;
    private Department $siblingUnitB;
    private Department $differentTypeUnitC;
    private Department $childOfA;
    private Department $grandchildOfA;

    protected function setUp(): void
    {
        parent::setUp();

        // unit_type_id has a real FK to org_unit_types, so this uses whichever
        // ids already exist in the dev DB rather than arbitrary numbers.
        $unitTypeIds = \Illuminate\Support\Facades\DB::table('org_unit_types')->orderBy('id')->pluck('id')->values();
        $this->assertGreaterThanOrEqual(3, $unitTypeIds->count(), 'Need at least 3 org_unit_types rows in the dev DB for this test.');
        [$typeForParentAndLeaves, $typeForAB, $typeForC] = [$unitTypeIds[0], $unitTypeIds[1], $unitTypeIds[2]];

        $this->grandParent = Department::create(['department_name' => 'PHPUnit GrandParent', 'unit_type_id' => $typeForParentAndLeaves, 'is_active' => true]);
        $this->unitA = Department::create(['department_name' => 'PHPUnit Unit A', 'unit_type_id' => $typeForAB, 'parent_id' => $this->grandParent->id, 'is_active' => true]);
        $this->siblingUnitB = Department::create(['department_name' => 'PHPUnit Unit B', 'unit_type_id' => $typeForAB, 'parent_id' => $this->grandParent->id, 'is_active' => true]);
        $this->differentTypeUnitC = Department::create(['department_name' => 'PHPUnit Unit C', 'unit_type_id' => $typeForC, 'parent_id' => $this->grandParent->id, 'is_active' => true]);
        $this->childOfA = Department::create(['department_name' => 'PHPUnit Child of A', 'unit_type_id' => $typeForParentAndLeaves, 'parent_id' => $this->unitA->id, 'is_active' => true]);
        $this->grandchildOfA = Department::create(['department_name' => 'PHPUnit Grandchild of A', 'unit_type_id' => $typeForParentAndLeaves, 'parent_id' => $this->childOfA->id, 'is_active' => true]);
    }

    public function test_self_only_returns_exactly_that_department(): void
    {
        $ids = app(OrgScopeService::class)->scopedDepartmentIds($this->unitA, UserOrgRole::SCOPE_SELF_ONLY);

        $this->assertSame([$this->unitA->id], $ids);
    }

    public function test_legacy_self_alias_behaves_like_self_only(): void
    {
        $ids = app(OrgScopeService::class)->scopedDepartmentIds($this->unitA, 'self');

        $this->assertSame([$this->unitA->id], $ids);
    }

    public function test_self_unit_only_returns_same_type_siblings_under_same_parent(): void
    {
        $ids = app(OrgScopeService::class)->scopedDepartmentIds($this->unitA, UserOrgRole::SCOPE_SELF_UNIT_ONLY);

        sort($ids);
        $expected = [$this->unitA->id, $this->siblingUnitB->id];
        sort($expected);
        $this->assertSame($expected, $ids, 'Must include A and its same-type sibling B, but not the different-type sibling C.');
        $this->assertNotContains($this->differentTypeUnitC->id, $ids);
    }

    public function test_all_scope_is_unrestricted(): void
    {
        $ids = app(OrgScopeService::class)->scopedDepartmentIds($this->unitA, UserOrgRole::SCOPE_ALL);

        $this->assertNull($ids);
    }

    public function test_self_and_children_includes_all_descendants(): void
    {
        $ids = app(OrgScopeService::class)->scopedDepartmentIds($this->unitA, UserOrgRole::SCOPE_SELF_AND_CHILDREN);

        sort($ids);
        $expected = [$this->unitA->id, $this->childOfA->id, $this->grandchildOfA->id];
        sort($expected);
        $this->assertSame($expected, $ids, 'Must include self, direct child, and grandchild.');
    }

    public function test_unrecognized_scope_type_defaults_to_self_and_children(): void
    {
        $ids = app(OrgScopeService::class)->scopedDepartmentIds($this->unitA, 'some_unrecognized_value');

        sort($ids);
        $expected = [$this->unitA->id, $this->childOfA->id, $this->grandchildOfA->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }
}
