<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Mission;
use Tests\TestCase;

/**
 * Mission management dashboard: pipeline counts match the queue pages, and
 * the year figures, today / upcoming / overdue panels reflect the missions.
 * Runs against the real (dev) database inside a rolled-back transaction, so
 * every figure is checked as a change from the baseline.
 */
class MissionDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private Department $office;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create([
            'full_name' => 'PHPUnit Mission Admin',
            'email' => 'phpunit_'.Str::lower(Str::random(10)).'@example.test',
            'password' => bcrypt('password'),
            'user_type_id' => 1,
        ]);
        $unitTypeId = (int) DB::table('org_unit_types')->orderBy('id')->value('id');
        $this->office = Department::create(['department_name' => 'PHPUnit Mission Dashboard Office', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
        $this->actingAs($this->admin);
    }

    private function employee(string $first): Employee
    {
        $employee = new Employee(['department_id' => $this->office->id, 'first_name' => $first, 'last_name' => 'PHPUnit', 'is_active' => 1]);
        $employee->employee_id = 'PHPUNIT-'.Str::upper(Str::random(8));
        $employee->save();

        return $employee;
    }

    private function mission(string $title, string $start, string $end, array $team, array $extra = []): Mission
    {
        $mission = Mission::create($extra + [
            'uuid' => (string) Str::uuid(), 'title' => $title, 'destination' => 'PHPUnit Kampot', 'purpose' => 'PHPUnit',
            'mission_type' => 'training', 'start_date' => $start, 'end_date' => $end,
            'status' => 'approved', 'requested_by' => $this->admin->id,
        ]);
        foreach ($team as $employee) {
            $mission->assignments()->create(['employee_id' => $employee->id, 'status' => 'active']);
        }

        return $mission;
    }

    private function dashboard(): \Illuminate\Testing\TestResponse
    {
        return $this->get(route('missions.dashboard'))->assertOk();
    }

    public function test_dashboard_reflects_pipeline_year_figures_and_panels(): void
    {
        $before = $this->dashboard();
        $stats = $before->viewData('stats');
        $counts = $before->viewData('counts');
        $overdue = $before->viewData('overdueCount');

        $a = $this->employee('Dara');
        $b = $this->employee('Sophea');
        $today = now()->toDateString();
        // On mission today: 2 officers, 3 days.
        $this->mission('PHPUnit today mission', now()->subDay()->toDateString(), now()->addDay()->toDateString(), [$a, $b]);
        // Starts in 3 days.
        $this->mission('PHPUnit upcoming mission', now()->addDays(3)->toDateString(), now()->addDays(3)->toDateString(), [$a]);
        // Ended last week, started in the field, nobody reported: overdue and waiting for a report.
        $late = $this->mission('PHPUnit overdue mission', now()->subDays(9)->toDateString(), now()->subDays(7)->toDateString(), [$b],
            ['started_at' => now()->subDays(9)]);
        // Report submitted, not yet closed by a manager.
        $reported = $this->mission('PHPUnit reported mission', now()->subDays(5)->toDateString(), now()->subDays(4)->toDateString(), [$a],
            ['started_at' => now()->subDays(5)]);
        $reported->reports()->make(['employee_id' => $a->id, 'summary' => 'PHPUnit report'])
            ->forceFill(['submitted_by' => $this->admin->id, 'submitted_at' => now()])->save();
        // A rejected request never counts as a mission that went ahead.
        $this->mission('PHPUnit rejected mission', $today, $today, [], ['status' => 'rejected']);

        $after = $this->dashboard();
        $after->assertSee('ផ្ទាំងគ្រប់គ្រងបេសកកម្ម')->assertSee('PHPUnit today mission')->assertSee('PHPUnit upcoming mission')
            ->assertSee('PHPUnit overdue mission')->assertSee('PHPUnit Dara')->assertSee('ក្នុង 3 ថ្ងៃ')->assertSee('ហួស 7 ថ្ងៃ')
            ->assertSee('ពិនិត្យ និងបិទបញ្ចប់');

        // Missions dated last week may fall in the previous year in early January.
        $thisYear = collect([now()->subDay(), now()->addDays(3), now()->subDays(9), now()->subDays(5)])
            ->filter(fn ($date) => $date->year === now()->year)->count();
        $statsAfter = $after->viewData('stats');
        $this->assertSame($stats['missions'] + $thisYear, $statsAfter['missions']);
        $this->assertSame($stats['officers'] + 2, $statsAfter['officers']);
        $this->assertSame($counts['pending-report'] + 1, $after->viewData('counts')['pending-report']);
        $this->assertSame($counts['report-review'] + 1, $after->viewData('counts')['report-review']);
        $this->assertSame($overdue + 1, $after->viewData('overdueCount'));
        $this->assertTrue($after->viewData('onMissionToday')->contains('title', 'PHPUnit today mission'));
        $this->assertFalse($after->viewData('onMissionToday')->contains('title', 'PHPUnit rejected mission'));

        // Each pipeline count is exactly what its queue page lists.
        foreach (['pending-report' => 'PHPUnit overdue mission', 'report-review' => 'PHPUnit reported mission'] as $key => $title) {
            $queue = $this->get(route('missions.queue', $key))->assertOk()->assertSee($title);
            $this->assertSame($after->viewData('counts')[$key], $queue->viewData('missions')->total());
        }
        $this->assertNotContains($late->id, $this->get(route('missions.queue', 'report-review'))->viewData('missions')->pluck('id'));
    }

    public function test_year_filter_and_sidebar_entry(): void
    {
        $this->mission('PHPUnit last year mission', now()->subYear()->startOfYear()->addDays(10)->toDateString(),
            now()->subYear()->startOfYear()->addDays(11)->toDateString(), [$this->employee('Vanna')]);

        $lastYear = $this->get(route('missions.dashboard', ['year' => now()->year - 1]))->assertOk();
        $this->assertSame(now()->year - 1, $lastYear->viewData('year'));
        $this->assertContains(now()->year - 1, $lastYear->viewData('years'));
        $this->assertGreaterThanOrEqual(1, $lastYear->viewData('stats')['missions']);
        $this->get(route('missions.dashboard', ['year' => 'abc']))->assertSessionHasErrors('year');

        // The sidebar's "mission management" entry now opens the dashboard.
        $this->dashboard()->assertSee('href="'.route('missions.dashboard').'" class="material-ripple"', false);
    }
}
