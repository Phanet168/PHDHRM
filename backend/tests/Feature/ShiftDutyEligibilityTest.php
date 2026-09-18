<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Shift;
use Tests\TestCase;

/**
 * Attendance Management Phase B: a duty/on-call shift (វេនយាម) may only be
 * created for a facility that serves patients around the clock (hospital,
 * health center) -- not the PHD provincial office or an operational
 * district's own administrative office, which run fixed hours. Enforced in
 * ShiftController::save() via AttendanceUnitScope::DUTY_ELIGIBLE_UNIT_TYPES.
 * Runs against the real (dev) database, wrapped in a transaction that is
 * rolled back afterwards.
 */
class ShiftDutyEligibilityTest extends TestCase
{
    use DatabaseTransactions;

    private const SUPER_ADMIN_ID = 25;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    private function admin(): User
    {
        return User::query()->findOrFail(self::SUPER_ADMIN_ID);
    }

    private function unitTypeId(string $code): int
    {
        return (int) \Illuminate\Support\Facades\DB::table('org_unit_types')->where('code', $code)->value('id');
    }

    private function shiftPayload(int $departmentId, bool $isDuty): array
    {
        return [
            'department_id' => $departmentId,
            'name' => 'PHPUnit Duty Eligibility Shift',
            'start_time' => '07:00',
            'end_time' => '16:00',
            'is_duty' => $isDuty,
            'is_active' => true,
        ];
    }

    public function test_duty_shift_is_rejected_for_an_administrative_phd_office(): void
    {
        $phd = Department::create([
            'department_name' => 'PHPUnit PHD Office',
            'unit_type_id' => $this->unitTypeId('phd'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('shifts.store'), $this->shiftPayload($phd->id, true));

        $response->assertStatus(422)->assertJsonValidationErrors('is_duty');
        $this->assertSame(0, Shift::where('department_id', $phd->id)->count());
    }

    public function test_duty_shift_is_rejected_for_an_operational_district_office(): void
    {
        $od = Department::create([
            'department_name' => 'PHPUnit Operational District',
            'unit_type_id' => $this->unitTypeId('operational_district'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('shifts.store'), $this->shiftPayload($od->id, true));

        $response->assertStatus(422)->assertJsonValidationErrors('is_duty');
    }

    public function test_duty_shift_is_accepted_for_a_health_center(): void
    {
        $healthCenter = Department::create([
            'department_name' => 'PHPUnit Health Center',
            'unit_type_id' => $this->unitTypeId('health_center'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('shifts.store'), $this->shiftPayload($healthCenter->id, true));

        $response->assertCreated();
        $this->assertSame(1, Shift::where('department_id', $healthCenter->id)->where('is_duty', true)->count());
    }

    public function test_non_duty_shift_is_still_accepted_for_a_phd_office(): void
    {
        // The gate is specifically on is_duty=true -- normal fixed-hours
        // shifts for administrative offices must be completely unaffected.
        $phd = Department::create([
            'department_name' => 'PHPUnit PHD Office Fixed Hours',
            'unit_type_id' => $this->unitTypeId('phd'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('shifts.store'), $this->shiftPayload($phd->id, false));

        $response->assertCreated();
    }
}
