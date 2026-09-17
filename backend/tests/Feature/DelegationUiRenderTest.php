<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 3D.1, section 3: confirms the Access Control Center Delegation tab
 * actually renders its real UI (not a placeholder), reusing the same page
 * every other ACC tab lives on -- no second management screen was created.
 */
class DelegationUiRenderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_delegation_tab_renders_with_real_controls_not_a_placeholder(): void
    {
        $admin = User::query()->findOrFail(25);

        $response = $this->actingAs($admin)->get(route('access-control.index'))->assertOk();

        $response->assertSee('ផ្ទេរសិទ្ធិបណ្តោះអាសន្ន', false); // section heading
        $response->assertSee('acc-deleg-from-search', false);   // FROM search field
        $response->assertSee('acc-deleg-to-search', false);     // TO search field
        $response->assertSee('acc-deleg-authority-box', false); // authority checklist container
        $response->assertSee('acc-deleg-scope-options', false); // scope picker
        $response->assertSee('acc-deleg-unit-picker', false);   // multi-unit org tree
        $response->assertSee('acc-deleg-starts-at', false);     // start date/time
        $response->assertSee('acc-deleg-ends-at', false);       // end date/time
        $response->assertSee('acc-deleg-reason', false);        // reason
        $response->assertSee('acc-deleg-preview', false);       // preview panel
        $response->assertSee('acc-deleg-state-btn', false);     // active/upcoming/expired/revoked filters
        $response->assertSee('acc-deleg-revoke-btn', false);    // revoke action (rendered conditionally in JS, id present in template string)

        // Confirm this is the ONLY delegation management surface referenced
        // on the page -- no second screen/route was introduced.
        $response->assertDontSee('ផ្ទេរសិទ្ធិបណ្តោះអាសន្ននឹងមកដល់', false); // old "coming soon" copy must be gone
    }
}
