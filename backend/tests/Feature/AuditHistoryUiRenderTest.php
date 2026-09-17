<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 3E, section 26: confirms the Access Control Center Audit History
 * tab actually renders its real UI (not the old "Coming Soon" placeholder),
 * reusing the same page every other ACC tab lives on -- no second history
 * screen was created.
 */
class AuditHistoryUiRenderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_audit_history_tab_renders_with_real_controls_not_a_placeholder(): void
    {
        $admin = User::query()->findOrFail(25);

        $response = $this->actingAs($admin)->get(route('access-control.index'))->assertOk();

        $response->assertSee('ប្រវត្តិសកម្មភាព', false);           // section heading
        $response->assertSee('acc-audit-filter-category', false); // category filter
        $response->assertSee('acc-audit-filter-actor', false);    // actor search
        $response->assertSee('acc-audit-filter-date-from', false);
        $response->assertSee('acc-audit-filter-date-to', false);
        $response->assertSee('acc-audit-filter-q', false);
        $response->assertSee('acc-audit-list', false);
        $response->assertSee('acc-audit-prev', false);
        $response->assertSee('acc-audit-next', false);

        $response->assertDontSee('ប្រវត្តិ — Coming Soon', false);
        $response->assertDontSee('ឆាប់ៗនេះ', false); // old tab badge text must be gone
    }
}
