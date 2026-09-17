<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use App\Services\OrganizationScopeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Tests\TestCase;

/**
 * Phase 3B: Organization Scope tests for the Access Control Center.
 * Runs against the project's real (dev) database, wrapped in a transaction
 * that is rolled back after every test -- nothing written here persists.
 *
 * Uses real, already-existing SystemRole (id 1 "head", id 2 "deputy_head")
 * and Department rows, since creating throwaway ones would need a full
 * departments/org-unit-types fixture that is out of scope here; the
 * transaction rollback makes reusing real reference rows safe.
 */
class AccessControlCenterScopeTest extends TestCase
{
    use DatabaseTransactions;

    private const SUPER_ADMIN_USER_ID = 25;
    private const NON_ADMIN_USER_ID = 640;
    private const RESPONSIBILITY_HEAD_ID = 1;
    private const RESPONSIBILITY_DEPUTY_HEAD_ID = 2;

    private array $deptIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->deptIds = \Modules\HumanResource\Entities\Department::query()
            ->where('is_active', 1)
            ->orderBy('id')
            ->limit(4)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->assertGreaterThanOrEqual(4, count($this->deptIds), 'Need at least 4 active departments in the dev DB for these tests.');
    }

    private function superAdmin(): User
    {
        return User::query()->findOrFail(self::SUPER_ADMIN_USER_ID);
    }

    private function nonAdmin(): User
    {
        return User::query()->findOrFail(self::NON_ADMIN_USER_ID);
    }

    private function makeAssignment(int $userId, int $responsibilityId, int $departmentId, string $scopeType, bool $isPrimary = false): UserAssignment
    {
        return app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $userId,
            'department_id' => $departmentId,
            'responsibility_id' => $responsibilityId,
            'scope_type' => $scopeType,
            'is_primary' => $isPrimary,
            'is_active' => true,
        ]);
    }

    // ---------------- Discovery-informed presentation mapping ----------------

    public function test_single_row_assignment_presents_as_its_own_scope_type(): void
    {
        $target = $this->nonAdmin();
        $this->makeAssignment($target->id, self::RESPONSIBILITY_HEAD_ID, $this->deptIds[0], UserAssignment::SCOPE_SELF_ONLY);

        $groups = app(OrganizationScopeService::class)->assignmentGroups($target);
        $this->assertCount(1, $groups);
        $this->assertSame(OrganizationScopeService::SELF, $groups[0]['presentation_scope']);
        $this->assertSame(1, $groups[0]['effective_unit_count']);
    }

    public function test_unit_tree_scope_expands_to_branch(): void
    {
        $target = $this->nonAdmin();
        $this->makeAssignment($target->id, self::RESPONSIBILITY_HEAD_ID, $this->deptIds[0], UserAssignment::SCOPE_SELF_AND_CHILDREN);

        $groups = app(OrganizationScopeService::class)->assignmentGroups($target);
        $this->assertSame(OrganizationScopeService::UNIT_TREE, $groups[0]['presentation_scope']);
        $this->assertGreaterThanOrEqual(1, $groups[0]['effective_unit_count']);
    }

    public function test_organization_scope_reports_null_effective_unit_count(): void
    {
        $target = $this->nonAdmin();
        $this->makeAssignment($target->id, self::RESPONSIBILITY_HEAD_ID, $this->deptIds[0], UserAssignment::SCOPE_ALL);

        $groups = app(OrganizationScopeService::class)->assignmentGroups($target);
        $this->assertSame(OrganizationScopeService::ORGANIZATION, $groups[0]['presentation_scope']);
        $this->assertNull($groups[0]['effective_unit_count']);
    }

    public function test_multiple_rows_for_one_responsibility_present_as_selected_units(): void
    {
        $target = $this->nonAdmin();
        $this->makeAssignment($target->id, self::RESPONSIBILITY_HEAD_ID, $this->deptIds[0], UserAssignment::SCOPE_SELF_ONLY);
        $this->makeAssignment($target->id, self::RESPONSIBILITY_HEAD_ID, $this->deptIds[1], UserAssignment::SCOPE_SELF_ONLY);

        $groups = app(OrganizationScopeService::class)->assignmentGroups($target);
        $this->assertCount(1, $groups, 'Both rows share the same responsibility, so they must collapse into ONE group.');
        $this->assertSame(OrganizationScopeService::SELECTED_UNITS, $groups[0]['presentation_scope']);
        $this->assertCount(2, $groups[0]['selected_departments']);
        $this->assertSame(2, $groups[0]['effective_unit_count']);
    }

    // ---------------- Editing via the controller ----------------

    public function test_editing_selected_units_creates_and_removes_rows_correctly(): void
    {
        $admin = $this->superAdmin();
        $target = $this->nonAdmin();
        $assignment = $this->makeAssignment($target->id, self::RESPONSIBILITY_HEAD_ID, $this->deptIds[0], UserAssignment::SCOPE_SELF_ONLY);
        $groupKey = $assignment->responsibility_id . '-0';

        $response = $this->actingAs($admin)->putJson(
            route('access-control.users.scopes.update', [$target->id, $groupKey]),
            ['scope_type' => 'SELECTED_UNITS', 'department_ids' => [$this->deptIds[0], $this->deptIds[1], $this->deptIds[2]]]
        )->assertOk();

        $data = $response->json('data');
        $this->assertSame('SELECTED_UNITS', $data['presentation_scope']);
        $this->assertCount(3, $data['selected_departments']);

        $activeCount = UserAssignment::where('user_id', $target->id)
            ->where('responsibility_id', self::RESPONSIBILITY_HEAD_ID)
            ->where('is_active', true)
            ->count();
        $this->assertSame(3, $activeCount);

        // Now shrink back down to a single unit -> should collapse the group to one row.
        $this->actingAs($admin)->putJson(
            route('access-control.users.scopes.update', [$target->id, $groupKey]),
            ['scope_type' => 'UNIT', 'department_ids' => [$this->deptIds[0]]]
        )->assertOk();

        $remaining = UserAssignment::where('user_id', $target->id)
            ->where('responsibility_id', self::RESPONSIBILITY_HEAD_ID)
            ->where('is_active', true)
            ->get();
        $this->assertCount(1, $remaining);
        $this->assertSame(UserAssignment::SCOPE_SELF_UNIT_ONLY, $remaining->first()->scope_type);
    }

    public function test_updating_one_assignment_group_does_not_affect_another(): void
    {
        $admin = $this->superAdmin();
        $target = $this->nonAdmin();
        $headAssignment = $this->makeAssignment($target->id, self::RESPONSIBILITY_HEAD_ID, $this->deptIds[0], UserAssignment::SCOPE_SELF_ONLY);
        $deputyAssignment = $this->makeAssignment($target->id, self::RESPONSIBILITY_DEPUTY_HEAD_ID, $this->deptIds[3], UserAssignment::SCOPE_ALL);

        $headGroupKey = $headAssignment->responsibility_id . '-0';

        $this->actingAs($admin)->putJson(
            route('access-control.users.scopes.update', [$target->id, $headGroupKey]),
            ['scope_type' => 'SELECTED_UNITS', 'department_ids' => [$this->deptIds[0], $this->deptIds[1]]]
        )->assertOk();

        $deputyRow = UserAssignment::find($deputyAssignment->id);
        $this->assertNotNull($deputyRow, 'The deputy_head assignment must still exist untouched.');
        $this->assertSame(UserAssignment::SCOPE_ALL, $deputyRow->scope_type);
        $this->assertSame($this->deptIds[3], $deputyRow->department_id);

        $groups = app(OrganizationScopeService::class)->assignmentGroups($target);
        $this->assertCount(2, $groups, 'Editing the head group must not merge or delete the deputy_head group.');
    }

    public function test_invalid_department_ids_are_rejected(): void
    {
        $admin = $this->superAdmin();
        $target = $this->nonAdmin();
        $assignment = $this->makeAssignment($target->id, self::RESPONSIBILITY_HEAD_ID, $this->deptIds[0], UserAssignment::SCOPE_SELF_ONLY);
        $groupKey = $assignment->responsibility_id . '-0';

        $this->actingAs($admin)->putJson(
            route('access-control.users.scopes.update', [$target->id, $groupKey]),
            ['scope_type' => 'SELECTED_UNITS', 'department_ids' => [999999999]]
        )->assertStatus(422);
    }

    public function test_updating_a_nonexistent_assignment_group_is_rejected(): void
    {
        $admin = $this->superAdmin();
        $target = $this->nonAdmin();

        $this->actingAs($admin)->putJson(
            route('access-control.users.scopes.update', [$target->id, '999999-0']),
            ['scope_type' => 'SELF', 'department_ids' => [$this->deptIds[0]]]
        )->assertStatus(422);
    }

    public function test_unauthorized_user_cannot_read_or_write_scope(): void
    {
        $nonAdmin = $this->nonAdmin();

        $this->actingAs($nonAdmin)
            ->getJson(route('access-control.users.scopes.index', $nonAdmin->id))
            ->assertForbidden();

        $this->actingAs($nonAdmin)
            ->putJson(route('access-control.users.scopes.update', [$nonAdmin->id, '1-0']), [
                'scope_type' => 'SELF', 'department_ids' => [$this->deptIds[0]],
            ])
            ->assertForbidden();
    }

    // ---------------- Integration with Users tab / Effective Access ----------------

    public function test_user_show_includes_scope_groups_summary(): void
    {
        $admin = $this->superAdmin();
        $target = $this->nonAdmin();
        $this->makeAssignment($target->id, self::RESPONSIBILITY_HEAD_ID, $this->deptIds[0], UserAssignment::SCOPE_SELF_ONLY);

        $this->actingAs($admin)
            ->getJson(route('access-control.users.show', $target->id))
            ->assertOk()
            ->assertJsonPath('data.scope_groups.0.presentation_scope', 'SELF');
    }

    public function test_org_tree_endpoint_returns_hierarchy_for_the_picker(): void
    {
        $this->actingAs($this->superAdmin())
            ->getJson(route('access-control.org-tree'))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'label', 'path', 'depth']]]);
    }

    // ---------------- Legacy governance UI is intentionally left in place ----------------

    public function test_legacy_org_governance_screen_still_renders_for_viewing_and_creating(): void
    {
        // Phase 3B.1 intentionally does not remove the existing HR governance
        // UserAssignment screen (see report section I/J/K) -- it still
        // handles creating brand-new responsibility assignments, which the
        // Organization Scope tab does not yet do. Confirm the route still
        // resolves successfully (not removed, not broken).
        $this->actingAs($this->superAdmin())
            ->get(route('user-assignments.index'))
            ->assertOk();
    }

    public function test_legacy_editor_can_no_longer_independently_change_scope(): void
    {
        // Phase 3B.1: the legacy /hr/user-assignments PUT (update) endpoint
        // must no longer be able to write a conflicting department_id/
        // scope_type for an EXISTING assignment -- Access Control Center is
        // now the only place that can. Other fields on the same assignment
        // (is_active) remain editable through the legacy form.
        $admin = $this->superAdmin();
        $target = $this->nonAdmin();
        $assignment = $this->makeAssignment($target->id, self::RESPONSIBILITY_HEAD_ID, $this->deptIds[0], UserAssignment::SCOPE_SELF_ONLY);

        $this->actingAs($admin)->put(route('user-assignments.update', $assignment->uuid), [
            'user_id' => $target->id,
            'department_id' => $this->deptIds[1], // attempted conflicting change
            'scope_type' => UserAssignment::SCOPE_ALL, // attempted conflicting change
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'is_primary' => '0',
            'is_active' => '0', // legitimate change this form still owns
        ])->assertRedirect();

        $fresh = UserAssignment::find($assignment->id);
        $this->assertSame($this->deptIds[0], $fresh->department_id, 'department_id must stay pinned to its stored value.');
        $this->assertSame(UserAssignment::SCOPE_SELF_ONLY, $fresh->scope_type, 'scope_type must stay pinned to its stored value.');
        $this->assertFalse((bool) $fresh->is_active, 'Non-scope fields must remain editable through the legacy form.');
    }
}
