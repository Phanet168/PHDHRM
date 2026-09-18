<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression guard for a real usability bug reported during manual review:
 * the Roles & Permissions (and Users tab direct-permission) checkbox matrix
 * showed every action for a resource with the SAME label (the resource's
 * display_name, e.g. "Org Governance" repeated 4 times for create/read/
 * update/delete), because PermissionCatalogService::entryFor()'s
 * display_name comes from the per_menu record, which is resource-scoped,
 * not action-scoped. Checkboxes were indistinguishable except for hover
 * tooltip or checked-state, which the user correctly flagged as unclear
 * compared to the legacy role-edit page's explicit Create/Read/Update/
 * Delete columns. Fixed by adding a Khmer action_label
 * (AccessControlCenterController::CATALOG_ACTION_LABELS_KM) and Khmer
 * module_label (CATALOG_MODULE_LABELS_KM), and rendering the matrix as an
 * actual resource x action table.
 */
class PermissionCatalogClarityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_catalog_gives_each_permission_a_distinct_khmer_action_label_not_a_repeated_resource_name(): void
    {
        $admin = User::query()->findOrFail(25);

        $data = $this->actingAs($admin)->getJson(route('access-control.catalog'))->assertOk()->json('data');

        $humanResource = collect($data)->firstWhere('module', 'human_resource');
        $this->assertNotNull($humanResource);
        $this->assertSame('ធនធានមនុស្ស', $humanResource['module_label'], 'Module label must be Khmer, not the raw module key or English label.');

        $orgGovernance = collect($humanResource['resources'])->firstWhere('resource', 'org_governance');
        $this->assertNotNull($orgGovernance);

        $labelsByAction = collect($orgGovernance['permissions'])->pluck('action_label', 'action');

        $this->assertSame('មើល', $labelsByAction['view']);
        $this->assertSame('បង្កើត', $labelsByAction['create']);
        $this->assertSame('កែប្រែ', $labelsByAction['update']);
        $this->assertSame('លុប', $labelsByAction['delete']);

        // The core bug: all 4 actions must NOT collapse to one identical label.
        $this->assertCount(4, $labelsByAction->unique(), 'Each action within a resource must have its own distinct label.');
    }

    public function test_permission_matrix_table_renders_distinct_action_columns_not_repeated_chips(): void
    {
        $admin = User::query()->findOrFail(25);

        $html = $this->actingAs($admin)->get(route('access-control.index'))->assertOk()->getContent();

        $this->assertStringContainsString('acc-perm-matrix', $html);
        $this->assertStringContainsString('CATALOG_CORE_ACTION_LABELS', $html);
        // The 4 core action column headers must be present as distinct Khmer labels.
        $this->assertMatchesRegularExpression('/មើល.*បង្កើត.*កែប្រែ.*លុប/s', $html);
    }
}
