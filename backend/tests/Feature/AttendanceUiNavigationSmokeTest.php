<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Attendance Management UI cleanup: attendance_header.blade.php and
 * reports_header.blade.php were flattened into far too many top-level tabs
 * (up to 13 in one row) as new phases were added; grouped the related ones
 * into dropdowns instead. Also completed the Phase C/D UI that the plan
 * called for but was never actually wired into shift-rosters/index.blade.php
 * (assign-by-team toggle, auto-generate-roster modal). This is a page-load
 * smoke test guarding that the regrouped nav partials and the new roster
 * page markup still compile and render for every page that includes them.
 */
class AttendanceUiNavigationSmokeTest extends TestCase
{
    use DatabaseTransactions;

    private const SUPER_ADMIN_ID = 25;

    public function test_attendance_pages_using_the_regrouped_nav_render_for_super_admin(): void
    {
        $admin = User::query()->findOrFail(self::SUPER_ADMIN_ID);
        $routes = [
            'attendances.workflow',
            'attendances.create',
            'attendances.monthlyCreate',
            'attendances.qrCreate',
            'shifts.index',
            'shift-rosters.index',
            'shift-teams.index',
            'missions.index',
            'attendances.exceptions',
            'attendance-snapshots.daily',
        ];

        foreach ($routes as $routeName) {
            $this->actingAs($admin)->get(route($routeName))->assertOk();
        }
    }

    public function test_report_pages_using_the_regrouped_nav_render_for_super_admin(): void
    {
        $admin = User::query()->findOrFail(self::SUPER_ADMIN_ID);
        $routes = [
            'reports.staff-attendance',
            'reports.leave',
            'reports.attendance-weekly',
            'reports.attendance-quarterly',
            'reports.attendance-semester',
            'reports.attendance-yearly',
        ];

        foreach ($routes as $routeName) {
            $this->actingAs($admin)->get(route($routeName))->assertOk();
        }
    }

    public function test_shift_roster_page_renders_the_team_assign_tab_and_generate_modal(): void
    {
        $admin = User::query()->findOrFail(self::SUPER_ADMIN_ID);

        $response = $this->actingAs($admin)->get(route('shift-rosters.index'));
        $response->assertOk();

        // Plain substring checks on purpose, not assertSee(): the roster
        // grid response can be large (many employees x 31 days), and
        // PHPUnit/Collision's diff renderer is pathologically slow at
        // pretty-printing a failure message for a multi-MB haystack.
        $content = $response->getContent();
        foreach ([
            'assign-team',
            'generate-roster-modal',
            route('shift-rosters.store-team'),
            route('shift-rosters.generate-preview'),
            route('shift-rosters.generate-commit'),
        ] as $needle) {
            $this->assertTrue(str_contains($content, $needle), "Response is missing expected content: {$needle}");
        }
    }
}
