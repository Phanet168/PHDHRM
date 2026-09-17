<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\UserOrgRole;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Tests\TestCase;

/**
 * Phase 3B.1 (scope consolidation): the legacy `/hr/user-org-roles` screen's
 * write actions (store/update/destroy) must no longer be able to create an
 * independent, unpinned generic-scope write path -- Access Control Center's
 * Organization Scope tab (and, for brand-new assignments,
 * UserAssignmentController) are the only writers now. The screen's `index`
 * (read) stays fully functional. Runs against the real (dev) database,
 * wrapped in a transaction that is rolled back afterwards.
 */
class ScopeConsolidationLegacyWritePathTest extends TestCase
{
    use DatabaseTransactions;

    private const SUPER_ADMIN_USER_ID = 25;
    private const NON_ADMIN_USER_ID = 640;
    private const RESPONSIBILITY_HEAD_ID = 1;

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

    private function makeSyncedLegacyRow(int $departmentId): UserOrgRole
    {
        $assignment = app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $this->nonAdmin()->id,
            'department_id' => $departmentId,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'is_primary' => false,
            'is_active' => true,
        ]);

        return $assignment->legacyOrgRole()->firstOrFail();
    }

    public function test_legacy_screen_still_renders_for_reading(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('user-org-roles.index'))
            ->assertOk();
    }

    public function test_legacy_store_no_longer_creates_a_row(): void
    {
        $admin = $this->superAdmin();
        $target = $this->nonAdmin();
        $countBefore = UserOrgRole::withoutGlobalScope('sortByLatest')->count();

        $this->actingAs($admin)->post(route('user-org-roles.store'), [
            'user_id' => $target->id,
            'department_id' => 1,
            'org_role' => 'head',
            'scope_type' => UserAssignment::SCOPE_ALL,
            'is_active' => 1,
        ])->assertRedirect(route('user-org-roles.index'));

        $this->assertSame($countBefore, UserOrgRole::withoutGlobalScope('sortByLatest')->count());
    }

    public function test_legacy_update_no_longer_changes_the_row(): void
    {
        $admin = $this->superAdmin();
        $legacyRow = $this->makeSyncedLegacyRow(1);

        $this->actingAs($admin)->put(route('user-org-roles.update', $legacyRow->uuid), [
            'user_id' => $legacyRow->user_id,
            'department_id' => 2, // attempted conflicting change
            'org_role' => 'deputy_head', // attempted conflicting change
            'scope_type' => UserAssignment::SCOPE_ALL, // attempted conflicting change
            'is_active' => 1,
        ])->assertRedirect(route('user-org-roles.index'));

        $fresh = UserOrgRole::withoutGlobalScope('sortByLatest')->find($legacyRow->id);
        $this->assertSame(1, $fresh->department_id, 'department_id must be unchanged.');
        $this->assertSame('head', $fresh->org_role, 'org_role must be unchanged.');
        $this->assertSame(UserAssignment::SCOPE_SELF_ONLY, $fresh->scope_type, 'scope_type must be unchanged.');
    }

    public function test_legacy_destroy_no_longer_deletes_the_row(): void
    {
        $admin = $this->superAdmin();
        $legacyRow = $this->makeSyncedLegacyRow(1);

        $this->actingAs($admin)
            ->deleteJson(route('user-org-roles.destroy', $legacyRow->uuid))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertNotNull(
            UserOrgRole::withoutGlobalScope('sortByLatest')->find($legacyRow->id),
            'The row must still exist after a blocked destroy call.'
        );
    }

    public function test_unauthorized_user_cannot_use_the_legacy_screen_to_write(): void
    {
        // This module's routes carry extra pre-existing middleware
        // (isAdmin + additional permission layers) beyond the controller's
        // own permission check, which redirects rather than aborting with
        // 403 -- that layering is unrelated to this fix. What matters here
        // is that a non-admin's write attempt never succeeds either way.
        $countBefore = UserOrgRole::withoutGlobalScope('sortByLatest')->count();

        $response = $this->actingAs($this->nonAdmin())->post(route('user-org-roles.store'), [
            'user_id' => $this->nonAdmin()->id,
            'department_id' => 1,
            'org_role' => 'head',
            'scope_type' => UserAssignment::SCOPE_ALL,
            'is_active' => 1,
        ]);

        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(201, $response->getStatusCode());
        $this->assertSame($countBefore, UserOrgRole::withoutGlobalScope('sortByLatest')->count());
    }
}
