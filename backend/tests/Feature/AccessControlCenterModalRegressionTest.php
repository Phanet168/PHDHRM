<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression guard for a real bug found during manual review: the vendored
 * Bootstrap build in public/backend/assets/plugins/bootstrap/js/
 * bootstrap.bundle.min.js is v5.0.0-beta1, whose BaseComponent constructor
 * does `this._element = element` with NO string-to-element resolution (that
 * convenience was only added in later Bootstrap 5 releases). Calling
 * `new bootstrap.Modal('#someId')` in this version stores the literal
 * string as `_element`; the very next line
 * (`SelectorEngine.findOne(SELECTOR_DIALOG, element)`) calls
 * `Element.prototype.querySelector.call(element, selector)` with `element`
 * bound to that string, which throws a TypeError synchronously inside the
 * constructor -- so `.show()` is never reached and the modal silently never
 * opens. This is invisible to Laravel's assertSee-based feature tests
 * (they never execute JS), which is exactly why it shipped unnoticed. Fixed
 * by passing `document.getElementById(...)` (an actual Element) instead of
 * a selector string; this test statically forbids the broken call shape
 * from reappearing on this page.
 */
class AccessControlCenterModalRegressionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_bootstrap_modal_is_never_constructed_with_a_raw_selector_string(): void
    {
        $admin = User::query()->findOrFail(25);

        $html = $this->actingAs($admin)->get(route('access-control.index'))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression(
            "/new\\s+bootstrap\\.Modal\\(\\s*['\"]/",
            $html,
            'new bootstrap.Modal(\'#id\') throws in the vendored Bootstrap 5.0.0-beta1 build -- always pass a real Element (e.g. document.getElementById(...)), never a selector string.'
        );

        // Both known modals on this page must use the working construction
        // shape at least once each, so this test would fail loudly if
        // either modal's open handler were ever removed entirely rather
        // than fixed.
        $this->assertStringContainsString("new bootstrap.Modal(document.getElementById('accRoleFormModal'))", $html);
        $this->assertStringContainsString("new bootstrap.Modal(document.getElementById('accDelegationFormModal'))", $html);
    }
}
