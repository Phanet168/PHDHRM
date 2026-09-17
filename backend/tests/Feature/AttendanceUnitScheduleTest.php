<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\HumanResource\Entities\Shift;
use Modules\HumanResource\Entities\ShiftRoster;
use Modules\HumanResource\Http\Controllers\AttendanceSnapshotController;
use Modules\HumanResource\Http\Controllers\ShiftController;
use Modules\HumanResource\Http\Controllers\ShiftRosterController;
use Modules\HumanResource\Services\AttendanceCaptureService;
use Modules\HumanResource\Services\AttendanceStatusService;
use Modules\HumanResource\Services\ShiftResolverService;
use Modules\HumanResource\Support\AttendanceUnitScope;
use Modules\HumanResource\Support\OrgHierarchyAccessService;
use Tests\TestCase;

class AttendanceUnitScheduleTest extends TestCase
{
    use \Tests\Support\BuildsAttendanceDatabase;

    public function test_manual_attendance_form_contains_only_selected_unit_employees(): void
    {
        $view = app(\Modules\HumanResource\Http\Controllers\ManualAttendanceController::class)
            ->create(app(\Modules\HumanResource\Support\OrgScopeService::class));
        $this->assertSame(1, $view->getData()['selectedDepartmentId']);
        $this->assertSame([1], $view->getData()['employee']->pluck('id')->all());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceDatabase();
        $user = new User;
        $user->forceFill(['id' => 99, 'user_type_id' => 1]);
        $this->actingAs($user);
        $access = \Mockery::mock(OrgHierarchyAccessService::class);
        $access->shouldReceive('managedBranchIds')->andReturn([1]);
        $access->shouldReceive('isSystemAdmin')->andReturn(true);
        $this->app->instance(OrgHierarchyAccessService::class, $access);
    }

    private function shift(array $data = []): Shift
    {
        return Shift::create($data + ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Work',
            'department_id' => 1, 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    }

    private function request(array $data): Request
    {
        return Request::create('/', 'POST', $data, [], [], ['HTTP_ACCEPT' => 'application/json']);
    }

    public function test_unit_scope_does_not_leak_child_unit_employees(): void
    {
        $this->assertSame([1], app(AttendanceUnitScope::class)->employees()->pluck('id')->all());
    }

    public function test_roster_rejects_shift_from_another_unit(): void
    {
        $shift = $this->shift(['department_id' => 2]);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(ShiftRosterController::class)->store($this->request(['employee_id' => 1, 'roster_date' => '2026-09-07', 'shift_id' => $shift->id]));
    }

    public function test_roster_rejects_foreign_employee_even_when_parent_unit_matches(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(ShiftRosterController::class)->store($this->request(['employee_id' => 2, 'roster_date' => '2026-09-07', 'is_day_off' => 1]));
    }

    public function test_roster_requires_one_unambiguous_choice(): void
    {
        $shift = $this->shift();
        $this->expectException(ValidationException::class);
        app(ShiftRosterController::class)->store($this->request(['employee_id' => 1, 'roster_date' => '2026-09-07', 'shift_id' => $shift->id, 'is_day_off' => 1]));
    }

    public function test_saving_same_roster_preserves_uuid_and_invalidates_summary(): void
    {
        $shift = $this->shift();
        $data = ['employee_id' => 1, 'roster_date' => '2026-09-07', 'shift_id' => $shift->id];
        $controller = app(ShiftRosterController::class);
        $controller->store($this->request($data));
        $uuid = ShiftRoster::first()->uuid;
        DB::table('attendance_daily_snapshots')->insert(['employee_id' => 1, 'snapshot_date' => '2026-09-07']);
        $controller->store($this->request($data));
        $this->assertSame(1, ShiftRoster::count());
        $this->assertSame($uuid, ShiftRoster::first()->uuid);
        $this->assertSame(0, DB::table('attendance_daily_snapshots')->count());
    }

    public function test_unit_default_applies_only_to_its_unit_and_duty_requires_roster(): void
    {
        $shift = $this->shift(['is_default' => true]);
        $resolver = app(ShiftResolverService::class);
        $day = Carbon::parse('2026-09-07');
        $this->assertSame($shift->id, $resolver->resolveForDate(1, $day)['shift']->id);
        $this->assertNull($resolver->resolveForDate(2, $day));
        $shift->update(['is_duty' => true]);
        $this->assertNull($resolver->resolveForDate(1, $day));
    }

    public function test_weekend_duty_uses_overnight_checkout_and_is_not_a_day_off(): void
    {
        DB::table('week_holidays')->insert(['dayname' => 'Saturday,Sunday']);
        $shift = $this->shift(['start_time' => '20:00', 'end_time' => '08:00', 'is_cross_day' => true, 'is_duty' => true]);
        ShiftRoster::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'employee_id' => 1, 'shift_id' => $shift->id, 'roster_date' => '2026-09-12']);
        AttendanceCaptureService::capture(['employee_id' => 1, 'time' => '2026-09-12 19:50:00']);
        $out = AttendanceCaptureService::capture(['employee_id' => 1, 'time' => '2026-09-13 08:05:00']);
        $this->assertSame(2, $out->machine_state);
        $result = app(AttendanceStatusService::class)->determineDailyStatus(1, Carbon::parse('2026-09-12'));
        $this->assertSame('Present', $result['attendance_status']);
        $this->assertSame(720, $result['worked_minutes']);
        $this->assertFalse($result['is_day_off']);
        $next = app(AttendanceStatusService::class)->determineDailyStatus(1, Carbon::parse('2026-09-13'));
        $this->assertNull($next['in_time']);
        $this->assertNull($next['out_time']);
    }

    public function test_explicit_roster_day_off_overrides_unit_default(): void
    {
        $this->shift(['is_default' => true]);
        ShiftRoster::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'employee_id' => 1, 'is_day_off' => true, 'roster_date' => '2026-09-07']);
        $result = app(AttendanceStatusService::class)->determineDailyStatus(1, Carbon::parse('2026-09-07'));
        $this->assertSame('Day Off', $result['attendance_status']);
        $this->assertNull($result['shift_id']);
    }

    public function test_afternoon_first_scan_is_in_even_if_morning_checkout_is_missing(): void
    {
        $this->shift(['is_default' => true, 'morning_end_time' => '12:00', 'afternoon_start_time' => '14:00']);
        AttendanceCaptureService::capture(['employee_id' => 1, 'time' => '2026-09-07 08:00:00']);
        $afternoon = AttendanceCaptureService::capture(['employee_id' => 1, 'time' => '2026-09-07 14:00:00']);
        $this->assertSame(1, $afternoon->machine_state);
    }

    public function test_shift_time_order_is_validated(): void
    {
        $this->expectException(ValidationException::class);
        app(ShiftController::class)->store($this->request(['department_id' => 1, 'name' => 'Invalid', 'start_time' => '08:00', 'end_time' => '17:00', 'morning_end_time' => '15:00', 'afternoon_start_time' => '14:00']));
    }

    public function test_regeneration_cannot_touch_another_unit(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(AttendanceSnapshotController::class)->regenerate($this->request(['department_id' => 1, 'employee_ids' => [2], 'start_date' => '2026-09-07', 'end_date' => '2026-09-07']));
    }

    public function test_empty_unit_scope_returns_no_employees(): void
    {
        $access = \Mockery::mock(OrgHierarchyAccessService::class);
        $access->shouldReceive('managedBranchIds')->andReturn([]);
        $scope = new AttendanceUnitScope($access);
        $this->assertSame(0, $scope->employees()->count());
        $this->assertSame(0, $scope->departments()->count());
    }

    public function test_saving_new_default_clears_old_default_and_accepts_empty_grace(): void
    {
        $first = $this->shift(['is_default' => true]);
        $response = app(ShiftController::class)->store($this->request(['department_id' => 1, 'name' => 'New default',
            'start_time' => '08:00', 'end_time' => '17:00', 'is_default' => 1, 'grace_late_minutes' => null]));
        $this->assertSame(201, $response->getStatusCode());
        $this->assertFalse($first->fresh()->is_default);
        $this->assertSame(1, Shift::where('department_id', 1)->where('is_default', true)->count());
    }

    public function test_overlapping_next_day_duty_is_rejected_atomically(): void
    {
        $night = $this->shift(['start_time' => '20:00', 'end_time' => '10:00', 'is_cross_day' => true, 'is_duty' => true]);
        $morning = $this->shift(['start_time' => '08:00', 'end_time' => '12:00']);
        ShiftRoster::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'employee_id' => 1, 'shift_id' => $morning->id, 'roster_date' => '2026-09-08']);
        try {
            app(ShiftRosterController::class)->store($this->request(['employee_id' => 1, 'roster_date' => '2026-09-07', 'shift_id' => $night->id]));
            $this->fail('Overlapping roster was accepted.');
        } catch (ValidationException $e) {
            $this->assertSame(1, ShiftRoster::count());
        }
    }

    public function test_used_shift_cannot_be_deleted(): void
    {
        $shift = $this->shift();
        ShiftRoster::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'employee_id' => 1, 'shift_id' => $shift->id, 'roster_date' => '2026-09-07']);
        $this->expectException(ValidationException::class);
        app(ShiftController::class)->destroy($this->request([]), $shift->id);
    }

    public function test_repeated_qr_scan_does_not_toggle_direction(): void
    {
        $first = AttendanceCaptureService::capture(['employee_id' => 1, 'time' => '2026-09-07 08:00:00', 'attendance_source' => 'api_qr']);
        $second = AttendanceCaptureService::capture(['employee_id' => 1, 'time' => '2026-09-07 08:00:20', 'attendance_source' => 'api_qr']);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, (int) $second->machine_state);
    }

    public function test_invalid_regeneration_date_is_validation_error(): void
    {
        $this->expectException(ValidationException::class);
        app(AttendanceSnapshotController::class)->regenerate($this->request(['start_date' => 'not-a-date', 'end_date' => '2026-09-07']));
    }

    public function test_daily_api_rejects_employee_outside_unit_scope(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(AttendanceSnapshotController::class)->daily($this->request(['employee_id' => 2, 'date' => '2026-09-07']));
    }

    public function test_checkout_at_next_shift_boundary_belongs_only_to_overnight_duty(): void
    {
        $this->shift(['is_default' => true]);
        $night = $this->shift(['start_time' => '20:00', 'end_time' => '08:00', 'is_cross_day' => true, 'is_duty' => true]);
        ShiftRoster::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'employee_id' => 1, 'shift_id' => $night->id, 'roster_date' => '2026-09-07']);
        AttendanceCaptureService::capture(['employee_id' => 1, 'time' => '2026-09-07 20:00:00']);
        $out = AttendanceCaptureService::capture(['employee_id' => 1, 'time' => '2026-09-08 08:00:00']);
        $this->assertSame(2, $out->machine_state);
        $previous = app(AttendanceStatusService::class)->determineDailyStatus(1, Carbon::parse('2026-09-07'));
        $next = app(AttendanceStatusService::class)->determineDailyStatus(1, Carbon::parse('2026-09-08'));
        $this->assertSame('Present', $previous['attendance_status']);
        $this->assertSame(720, $previous['worked_minutes']);
        $this->assertNull($next['out_time']);
    }
}
