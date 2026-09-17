<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Shift;
use Modules\HumanResource\Entities\ShiftRoster;
use Modules\HumanResource\Http\Controllers\AttendanceDashboardController;
use Modules\HumanResource\Services\AttendanceDashboardService;
use Modules\HumanResource\Support\OrgHierarchyAccessService;
use Tests\Support\BuildsAttendanceDatabase;
use Tests\TestCase;

class AttendanceDashboardTest extends TestCase
{
    use BuildsAttendanceDatabase;

    public function test_dashboard_rejects_user_without_management_permission(): void
    {
        $access = \Mockery::mock(OrgHierarchyAccessService::class);
        $access->shouldReceive('isSystemAdmin')->andReturn(false);
        $this->app->instance(OrgHierarchyAccessService::class, $access);
        Gate::shouldReceive('forUser')->andReturnSelf();
        Gate::shouldReceive('check')->andReturn(false);
        $this->getJson('/api/v1/attendance/dashboard?department_id=1')->assertForbidden();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceDatabase();
        Carbon::setTestNow(Carbon::parse('2026-09-08 10:00:00'));
        $user = new User;
        $user->forceFill(['id' => 99, 'user_type_id' => 1]);
        \Laravel\Sanctum\Sanctum::actingAs($user);
        $access = \Mockery::mock(OrgHierarchyAccessService::class);
        $access->shouldReceive('managedBranchIds')->andReturn([1]);
        $access->shouldReceive('isSystemAdmin')->andReturn(true);
        $this->app->instance(OrgHierarchyAccessService::class, $access);
        Shift::create(['uuid' => (string) Str::uuid(), 'name' => 'Office', 'department_id' => 1, 'is_default' => true,
            'start_time' => '08:00', 'morning_end_time' => '12:00', 'afternoon_start_time' => '14:00', 'end_time' => '17:00', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function dashboard(): array
    {
        return app(AttendanceDashboardService::class)->build(Employee::where('id', 1)->get(), Carbon::parse('2026-09-08'));
    }

    public function test_waiting_officer_is_not_final_absence_during_open_session(): void
    {
        $data = $this->dashboard();
        $this->assertSame(1, $data['summary']['waiting']);
        $this->assertSame(0, $data['summary']['absent']);
        $this->assertSame(0, $data['summary']['attention']);
    }

    public function test_missing_morning_is_incomplete_before_afternoon_finishes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-08 14:30:00'));
        $data = $this->dashboard();
        $this->assertSame(1, $data['summary']['incomplete']);
        $this->assertSame(0, $data['summary']['absent']);
        $this->assertSame(1, $data['summary']['attention']);
    }

    public function test_absence_is_final_after_work_ends_and_day_off_is_excused(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-08 18:00:00'));
        $this->assertSame(1, $this->dashboard()['summary']['absent']);
        ShiftRoster::create(['uuid' => (string) Str::uuid(), 'employee_id' => 1, 'roster_date' => '2026-09-08', 'is_day_off' => true]);
        $data = $this->dashboard();
        $this->assertSame(0, $data['summary']['expected']);
        $this->assertSame(0, $data['summary']['attention']);
        $this->assertSame(1, $data['summary']['off']);
    }

    public function test_dashboard_api_separates_units_and_requires_explicit_selection(): void
    {
        $this->getJson('/api/v1/attendance/dashboard')->assertUnprocessable();
        $this->getJson('/api/v1/attendance/dashboard?department_id=2')->assertNotFound();
        $this->getJson('/api/v1/attendance/dashboard?department_id=1')->assertOk()
            ->assertJsonPath('response.data.summary.total', 1)->assertJsonCount(1, 'response.data.records.data');
    }

    public function test_one_officer_with_multiple_issues_is_counted_once(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-08 18:00:00'));
        foreach ([['08:20', 1], ['11:40', 2], ['14:00', 1], ['17:00', 2]] as [$time, $state]) {
            DB::table('attendances')->insert(['employee_id' => 1, 'time' => '2026-09-08 '.$time.':00', 'machine_state' => $state]);
        }
        $data = $this->dashboard();
        $this->assertSame(1, $data['summary']['late']);
        $this->assertSame(1, $data['summary']['early_leave']);
        $this->assertSame(1, $data['summary']['attention']);
        $this->getJson('/api/v1/attendance/dashboard?department_id=1&status=absent')->assertOk()->assertJsonCount(0, 'response.data.records.data');
    }

    public function test_dashboard_body_renders_summary_without_employee_table(): void
    {
        Gate::shouldReceive('check')->andReturn(true);
        DB::table('employees')->where('id', 1)->update(['first_name' => '<script>bad</script>']);
        $request = \Illuminate\Http\Request::create('/hr/attendances/workflow', 'GET', ['department_id' => 1]);
        $view = app(AttendanceDashboardController::class)->index($request);
        $template = file_get_contents(base_path('modules/HumanResource/Resources/views/attendance/dashboard.blade.php'));
        $template = preg_replace('/^@extends.*$/m', '', $template);
        $template = preg_replace('/@include\([^\n]+\)/', '', $template);
        $template = str_replace("@section('content')", '', $template);
        $template = str_replace('@endsection', '', $template);
        $html = Blade::render($template, $view->getData());
        $this->assertStringContainsString('សង្ខេបស្ថានភាពវត្តមាន', $html);
        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringNotContainsString('name="q"', $html);
        $this->assertStringNotContainsString('&lt;script&gt;bad&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>bad</script>', $html);
    }
}
