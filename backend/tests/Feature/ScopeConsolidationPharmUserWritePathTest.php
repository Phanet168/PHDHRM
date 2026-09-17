<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\UserOrgRole;
use Tests\TestCase;

/**
 * Phase 3B.2, section 45 final-duplication-search finding: PharmUserController
 * wrote directly to UserOrgRole::create()/update()/delete(), a THIRD
 * independent generic-scope writer (alongside the now-closed legacy
 * /hr/user-org-roles screen) that never touched the canonical
 * UserAssignment table at all. Now routed through GovernanceAssignmentService.
 * These tests confirm store/toggle/destroy all now also create/update/delete
 * a linked canonical UserAssignment row, not just the legacy mirror -- and
 * that the existing index/list screen (which reads UserOrgRole directly)
 * still works unchanged. Runs against the real (dev) database, wrapped in a
 * transaction that is rolled back afterwards.
 */
class ScopeConsolidationPharmUserWritePathTest extends TestCase
{
    use DatabaseTransactions;

    private const SUPER_ADMIN_USER_ID = 25;
    private const TARGET_USER_ID = 640;
    private const PHARM_DEPARTMENT_ID = 15; // real, active, unit_type_id=1 (PHD)

    private function superAdmin(): User
    {
        return User::query()->findOrFail(self::SUPER_ADMIN_USER_ID);
    }

    public function test_store_creates_a_linked_canonical_user_assignment(): void
    {
        $this->actingAs($this->superAdmin())->post(route('pharmaceutical.users.store'), [
            'user_id' => self::TARGET_USER_ID,
            'department_id' => self::PHARM_DEPARTMENT_ID,
            'org_role' => 'head',
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
        ])->assertRedirect(route('pharmaceutical.users.index'));

        $legacyRow = UserOrgRole::withoutGlobalScopes()
            ->where('user_id', self::TARGET_USER_ID)
            ->where('department_id', self::PHARM_DEPARTMENT_ID)
            ->where('is_active', true)
            ->firstOrFail();

        $this->assertNotNull($legacyRow->user_assignment_id, 'store() must now link a canonical UserAssignment, not write UserOrgRole in isolation.');

        $assignment = UserAssignment::withoutGlobalScope('sortByLatest')->find($legacyRow->user_assignment_id);
        $this->assertNotNull($assignment);
        $this->assertSame(self::TARGET_USER_ID, $assignment->user_id);
        $this->assertSame(self::PHARM_DEPARTMENT_ID, $assignment->department_id);
        $this->assertSame(UserAssignment::SCOPE_SELF_ONLY, $assignment->scope_type);
    }

    public function test_toggle_deactivates_both_legacy_and_canonical_rows(): void
    {
        $this->actingAs($this->superAdmin())->post(route('pharmaceutical.users.store'), [
            'user_id' => self::TARGET_USER_ID,
            'department_id' => self::PHARM_DEPARTMENT_ID,
            'org_role' => 'head',
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
        ]);

        $legacyRow = UserOrgRole::withoutGlobalScopes()
            ->where('user_id', self::TARGET_USER_ID)
            ->where('department_id', self::PHARM_DEPARTMENT_ID)
            ->firstOrFail();

        $this->actingAs($this->superAdmin())
            ->patch(route('pharmaceutical.users.toggle', $legacyRow->uuid))
            ->assertRedirect();

        $legacyRow->refresh();
        $assignment = UserAssignment::withoutGlobalScope('sortByLatest')->find($legacyRow->user_assignment_id);

        $this->assertFalse((bool) $legacyRow->is_active, 'Legacy mirror row must be deactivated.');
        $this->assertNotNull($assignment);
        $this->assertFalse((bool) $assignment->is_active, 'Canonical UserAssignment must be deactivated in lockstep, not left stale.');
    }

    public function test_destroy_removes_both_legacy_and_canonical_rows(): void
    {
        $this->actingAs($this->superAdmin())->post(route('pharmaceutical.users.store'), [
            'user_id' => self::TARGET_USER_ID,
            'department_id' => self::PHARM_DEPARTMENT_ID,
            'org_role' => 'head',
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
        ]);

        $legacyRow = UserOrgRole::withoutGlobalScopes()
            ->where('user_id', self::TARGET_USER_ID)
            ->where('department_id', self::PHARM_DEPARTMENT_ID)
            ->firstOrFail();
        $assignmentId = $legacyRow->user_assignment_id;

        $this->actingAs($this->superAdmin())
            ->delete(route('pharmaceutical.users.destroy', $legacyRow->uuid))
            ->assertRedirect();

        $deletedLegacyRow = UserOrgRole::withoutGlobalScopes()->withTrashed()->find($legacyRow->id);
        $this->assertNotNull($deletedLegacyRow?->deleted_at, 'Legacy mirror row must be soft-deleted.');

        $deletedAssignment = UserAssignment::withoutGlobalScope('sortByLatest')->withTrashed()->find($assignmentId);
        $this->assertNotNull($deletedAssignment?->deleted_at, 'Canonical UserAssignment must be soft-deleted too, not left behind.');
    }

    public function test_index_listing_still_works_after_creating_via_canonical_path(): void
    {
        $this->actingAs($this->superAdmin())->post(route('pharmaceutical.users.store'), [
            'user_id' => self::TARGET_USER_ID,
            'department_id' => self::PHARM_DEPARTMENT_ID,
            'org_role' => 'head',
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('pharmaceutical.users.index'))
            ->assertOk();
    }
}
