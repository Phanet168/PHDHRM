<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Modules\HumanResource\Entities\Employee;
use Tests\TestCase;

class AttendanceV1SmokeTest extends TestCase
{
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

        Sanctum::actingAs($user);

        $today = Carbon::today()->toDateString();
        $suffix = now()->format('His');

        $createShift = $this->postJson('/api/v1/shifts', [
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

        $this->getJson('/api/v1/shifts')
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->postJson('/api/v1/shift-rosters', [
            'employee_id' => (int) $employeeId,
            'roster_date' => $today,
            'shift_id' => $shiftId,
            'is_day_off' => false,
            'is_holiday' => false,
            'note' => 'Smoke test roster',
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'ok');

        $this->getJson('/api/v1/shift-rosters?employee_id=' . $employeeId . '&date=' . $today)
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->postJson('/api/v1/missions', [
            'title' => 'Smoke Mission ' . $suffix,
            'start_date' => $today,
            'end_date' => $today,
            'destination' => 'HQ',
            'purpose' => 'Smoke test mission',
            'status' => 'approved',
            'employee_ids' => [(int) $employeeId],
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'ok');

        $this->getJson('/api/v1/missions')
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->postJson('/api/v1/attendance-adjustments', [
            'employee_id' => (int) $employeeId,
            'reason' => 'Smoke test adjustment',
        ])
            ->assertCreated()
            ->assertJsonPath('result.status', 'ok');

        $this->getJson('/api/v1/attendance-adjustments')
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->getJson('/api/v1/attendance-snapshots/daily?employee_id=' . $employeeId . '&date=' . $today . '&recompute=1')
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->postJson('/api/v1/attendance-snapshots/regenerate', [
            'start_date' => $today,
            'end_date' => $today,
            'employee_ids' => [(int) $employeeId],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }
}
