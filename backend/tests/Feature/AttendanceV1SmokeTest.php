<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Modules\HumanResource\Entities\Employee;
use Tests\TestCase;

class AttendanceV1SmokeTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;
    public function test_v1_attendance_api_smoke_flow(): void
    {
        $user = User::query()->first();
        if (!$user) {
            $this->markTestSkipped('No user record found for Sanctum authentication.');
        }

        $employeeId = Employee::query()->value('id');
        if (!$employeeId) {
            $this->markTestSkipped('No employee record found for attendance payloads.');
        }

        $employee = Employee::findOrFail($employeeId);
        $departmentId = (int) ($employee->sub_department_id ?: $employee->department_id);
        if (!$departmentId) {
            $this->markTestSkipped('The attendance smoke fixture needs an assigned organization unit.');
        }

        Sanctum::actingAs($user);

        $today = Carbon::today()->toDateString();
        $suffix = now()->format('His');

        $createShift = $this->postJson(route('api.v1.shifts.store'), [
            'department_id' => $departmentId,
            'code' => 'SMK-' . $suffix,
            'name' => 'Smoke Shift ' . $suffix,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'grace_late_minutes' => 10,
            'grace_early_leave_minutes' => 5,
            'is_active' => true,
        ]);

        $createShift->assertCreated()->assertJsonPath('status', 'ok');
        $shiftId = (int) data_get($createShift->json(), 'data.id');
        $this->assertGreaterThan(0, $shiftId, 'Shift ID should be returned after creation.');

        $this->getJson(route('api.v1.shifts.index'))
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->postJson(route('api.v1.shift_rosters.store'), [
            'employee_id' => (int) $employeeId,
            'roster_date' => $today,
            'shift_id' => $shiftId,
            'is_day_off' => false,
            'is_holiday' => false,
            'note' => 'Smoke test roster',
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'ok');

        $this->getJson(route('api.v1.shift_rosters.index', [
            'employee_id' => $employeeId,
            'date' => $today,
        ]))
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->postJson(route('api.v1.missions.store'), [
            'title' => 'Smoke Mission ' . $suffix,
            'start_date' => $today,
            'end_date' => $today,
            'destination' => 'HQ',
            'purpose' => 'Smoke test mission',
            'status' => 'pending',
            'employee_ids' => [(int) $employeeId],
        ])
            ->assertCreated()
            ->assertJsonPath('response.status', 'ok');

        $this->getJson(route('api.v1.missions.index'))
            ->assertOk()
            ->assertJsonPath('response.status', 'ok');

        $this->postJson(route('api.v1.attendance_adjustments.store'), [
            'employee_id' => (int) $employeeId,
            'reason' => 'Smoke test adjustment',
        ])
            ->assertCreated()
            ->assertJsonPath('result.status', 'ok');

        $this->getJson(route('api.v1.attendance_adjustments.index'))
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->getJson(route('api.v1.attendance_snapshots.daily', [
            'employee_id' => $employeeId,
            'date' => $today,
            'recompute' => 1,
        ]))
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->postJson(route('api.v1.attendance_snapshots.regenerate'), [
            'start_date' => $today,
            'end_date' => $today,
            'employee_ids' => [(int) $employeeId],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }
}
