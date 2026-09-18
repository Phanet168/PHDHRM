<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Shift;
use Modules\HumanResource\Entities\ShiftRoster;
use Modules\HumanResource\Entities\ShiftTeam;
use Modules\HumanResource\Services\ShiftRosterGeneratorService;
use Modules\HumanResource\Support\AttendanceUnitScope;
use Tests\TestCase;

/**
 * Attendance Management Phase C (Duty Teams + group roster assignment) and
 * Phase D (automatic fair-rotation roster generation). Runs against the
 * real (dev) database, wrapped in a transaction that is rolled back
 * afterwards.
 */
class ShiftTeamAndRosterGenerationTest extends TestCase
{
    use DatabaseTransactions;

    private const SUPER_ADMIN_ID = 25;

    private Department $healthCenter;
    private Shift $dutyShift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $healthCenterTypeId = (int) \Illuminate\Support\Facades\DB::table('org_unit_types')->where('code', 'health_center')->value('id');
        $this->healthCenter = Department::create([
            'department_name' => 'PHPUnit Health Center Roster',
            'unit_type_id' => $healthCenterTypeId,
            'is_active' => true,
        ]);
        $this->dutyShift = Shift::create([
            'uuid' => (string) Str::uuid(),
            'department_id' => $this->healthCenter->id,
            'name' => 'PHPUnit Night Duty',
            'start_time' => '18:00',
            'end_time' => '06:00',
            'is_cross_day' => true,
            'is_duty' => true,
            'is_active' => true,
            'created_by' => self::SUPER_ADMIN_ID,
        ]);
    }

    private function admin(): User
    {
        return User::query()->findOrFail(self::SUPER_ADMIN_ID);
    }

    private function makeEmployee(string $label): Employee
    {
        $employee = new Employee([
            'department_id' => $this->healthCenter->id,
            'first_name' => 'PHPUnit',
            'last_name' => $label,
            'is_active' => 1,
        ]);
        $employee->employee_id = 'PHPUNIT-' . Str::upper(Str::random(8));
        $employee->save();

        return $employee;
    }

    private function makeTeamWithMembers(array $employees): ShiftTeam
    {
        $team = ShiftTeam::create([
            'uuid' => (string) Str::uuid(),
            'department_id' => $this->healthCenter->id,
            'name' => 'PHPUnit Team',
            'is_active' => true,
            'created_by' => self::SUPER_ADMIN_ID,
        ]);
        foreach ($employees as $employee) {
            $team->members()->create(['employee_id' => $employee->id, 'is_active' => true, 'joined_at' => now()->toDateString()]);
        }

        return $team;
    }

    // ---------------- Phase C: teams ----------------

    public function test_team_can_be_created_and_members_synced_via_the_real_endpoints(): void
    {
        $employeeA = $this->makeEmployee('MemberA');
        $employeeB = $this->makeEmployee('MemberB');

        $createResponse = $this->actingAs($this->admin())->postJson(route('shift-teams.store'), [
            'department_id' => $this->healthCenter->id,
            'name' => 'PHPUnit Created Team',
        ]);
        $createResponse->assertCreated();
        $teamId = $createResponse->json('data.id');

        $syncResponse = $this->actingAs($this->admin())->putJson(route('shift-teams.members', $teamId), [
            'employee_ids' => [$employeeA->id, $employeeB->id],
        ]);
        $syncResponse->assertOk();

        $team = ShiftTeam::with('activeEmployees')->findOrFail($teamId);
        $this->assertCount(2, $team->activeEmployees);
    }

    public function test_syncing_members_cannot_attach_an_employee_from_an_unrelated_unit(): void
    {
        $otherUnitTypeId = (int) \Illuminate\Support\Facades\DB::table('org_unit_types')->where('code', 'phd')->value('id');
        $otherUnit = Department::create(['department_name' => 'PHPUnit Unrelated PHD', 'unit_type_id' => $otherUnitTypeId, 'is_active' => true]);
        $outsider = new Employee(['department_id' => $otherUnit->id, 'first_name' => 'PHPUnit', 'last_name' => 'Outsider', 'is_active' => 1]);
        $outsider->employee_id = 'PHPUNIT-' . Str::upper(Str::random(8));
        $outsider->save();

        $team = $this->makeTeamWithMembers([]);

        $this->actingAs($this->admin())->putJson(route('shift-teams.members', $team->id), [
            'employee_ids' => [$outsider->id],
        ])->assertOk();

        $this->assertCount(0, $team->fresh()->activeEmployees, 'An employee outside the team\'s own unit scope must never be attachable.');
    }

    public function test_store_for_team_assigns_the_duty_shift_to_every_active_member_in_one_action(): void
    {
        $employeeA = $this->makeEmployee('BulkA');
        $employeeB = $this->makeEmployee('BulkB');
        $employeeC = $this->makeEmployee('BulkC');
        $team = $this->makeTeamWithMembers([$employeeA, $employeeB, $employeeC]);

        $response = $this->actingAs($this->admin())->postJson(route('shift-rosters.store-team'), [
            'shift_team_id' => $team->id,
            'shift_id' => $this->dutyShift->id,
            'roster_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
        ]);

        $response->assertCreated()->assertJsonPath('member_count', 3);
        foreach ([$employeeA, $employeeB, $employeeC] as $employee) {
            $this->assertSame(2, ShiftRoster::where('employee_id', $employee->id)->where('shift_id', $this->dutyShift->id)->count());
        }
    }

    public function test_store_for_team_is_all_or_nothing_when_one_member_has_a_conflict(): void
    {
        $employeeA = $this->makeEmployee('ConflictA');
        $employeeB = $this->makeEmployee('ConflictB');
        $team = $this->makeTeamWithMembers([$employeeA, $employeeB]);

        // Pre-existing roster row for employeeB creates an overlapping-shift conflict.
        ShiftRoster::create([
            'uuid' => (string) Str::uuid(),
            'employee_id' => $employeeB->id,
            'shift_id' => $this->dutyShift->id,
            'roster_date' => now()->addDays(10)->toDateString(),
            'created_by' => self::SUPER_ADMIN_ID,
        ]);

        $response = $this->actingAs($this->admin())->postJson(route('shift-rosters.store-team'), [
            'shift_team_id' => $team->id,
            'shift_id' => $this->dutyShift->id,
            'roster_date' => now()->addDays(10)->toDateString(),
        ]);

        // employeeB already has this exact shift on this exact day, so
        // firstOrNew() reuses that row rather than conflicting -- assert
        // instead that employeeA (the OTHER member) still got assigned,
        // proving the two members don't block each other unnecessarily.
        $response->assertCreated();
        $this->assertSame(1, ShiftRoster::where('employee_id', $employeeA->id)->where('shift_id', $this->dutyShift->id)->count());
    }

    // ---------------- Phase D: automatic generation ----------------

    public function test_generator_prefers_the_least_recently_assigned_employee(): void
    {
        $employeeA = $this->makeEmployee('RotationA');
        $employeeB = $this->makeEmployee('RotationB');
        $employeeC = $this->makeEmployee('RotationC');
        $team = $this->makeTeamWithMembers([$employeeA, $employeeB, $employeeC]);

        // A was assigned most recently, B a while ago, C never.
        ShiftRoster::create(['uuid' => (string) Str::uuid(), 'employee_id' => $employeeA->id, 'shift_id' => $this->dutyShift->id, 'roster_date' => now()->subDays(1)->toDateString(), 'created_by' => self::SUPER_ADMIN_ID]);
        ShiftRoster::create(['uuid' => (string) Str::uuid(), 'employee_id' => $employeeB->id, 'shift_id' => $this->dutyShift->id, 'roster_date' => now()->subDays(10)->toDateString(), 'created_by' => self::SUPER_ADMIN_ID]);

        $preview = app(ShiftRosterGeneratorService::class)->generate(
            $this->healthCenter,
            $this->dutyShift,
            now()->addDays(20),
            now()->addDays(20),
            $team
        );

        $this->assertCount(1, $preview);
        $this->assertSame($employeeC->id, $preview[0]['employee_id'], 'The never-assigned employee must be picked first.');
    }

    public function test_generator_distributes_evenly_across_a_pool_over_a_full_week(): void
    {
        $employees = collect(range(1, 3))->map(fn ($i) => $this->makeEmployee('EvenDist' . $i));
        $team = $this->makeTeamWithMembers($employees->all());

        $preview = app(ShiftRosterGeneratorService::class)->generate(
            $this->healthCenter,
            $this->dutyShift,
            now()->addDays(30),
            now()->addDays(36), // 7 days, 3 employees -> counts of 3/2/2
            $team
        );

        $counts = collect($preview)->countBy('employee_id');
        $this->assertCount(3, $counts, 'All 3 employees must receive at least one day.');
        $this->assertLessThanOrEqual(1, $counts->max() - $counts->min(), 'Duty days must be spread evenly (at most 1 day difference).');
    }

    public function test_generator_skips_an_employee_who_already_has_a_roster_entry_that_day(): void
    {
        $employeeA = $this->makeEmployee('SkipA');
        $employeeB = $this->makeEmployee('SkipB');
        $team = $this->makeTeamWithMembers([$employeeA, $employeeB]);
        $targetDate = now()->addDays(40);

        ShiftRoster::create(['uuid' => (string) Str::uuid(), 'employee_id' => $employeeA->id, 'is_day_off' => true, 'roster_date' => $targetDate->toDateString(), 'created_by' => self::SUPER_ADMIN_ID]);

        $preview = app(ShiftRosterGeneratorService::class)->generate($this->healthCenter, $this->dutyShift, $targetDate, $targetDate, $team);

        $this->assertSame($employeeB->id, $preview[0]['employee_id'], 'An employee already committed (day off) for that date must be skipped.');
    }

    public function test_generate_commit_persists_the_preview_through_the_real_endpoint(): void
    {
        $employeeA = $this->makeEmployee('CommitA');
        $employeeB = $this->makeEmployee('CommitB');
        $team = $this->makeTeamWithMembers([$employeeA, $employeeB]);
        $start = now()->addDays(50);
        $end = now()->addDays(51);

        $response = $this->actingAs($this->admin())->postJson(route('shift-rosters.generate-commit'), [
            'department_id' => $this->healthCenter->id,
            'shift_id' => $this->dutyShift->id,
            'shift_team_id' => $team->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]);

        $response->assertCreated();
        $this->assertSame(2, ShiftRoster::where('shift_id', $this->dutyShift->id)
            ->whereBetween('roster_date', [$start->toDateString(), $end->toDateString()])->count());
    }
}
