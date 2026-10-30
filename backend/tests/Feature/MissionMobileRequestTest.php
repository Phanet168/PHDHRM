<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Mission;
use Modules\HumanResource\Enums\Mission\MissionCreationPath;
use Tests\TestCase;

/**
 * Mobile mission request (Flow A): an ordinary employee — no unit to manage —
 * can request a mission for themselves and colleagues of their own unit; the
 * request then enters the same office-head/director workflow as the web form.
 * Runs against the real (dev) database inside a rolled-back transaction.
 */
class MissionMobileRequestTest extends TestCase
{
    use DatabaseTransactions;

    private Department $office;

    private Department $otherOffice;

    protected function setUp(): void
    {
        parent::setUp();
        $unitTypeId = (int) DB::table('org_unit_types')->orderBy('id')->value('id');
        $this->office = Department::create(['department_name' => 'PHPUnit Mission Request Office', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
        $this->otherOffice = Department::create(['department_name' => 'PHPUnit Mission Request Other', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
    }

    private function employee(Department $unit, string $label, ?User $user = null): Employee
    {
        $employee = new Employee([
            'user_id' => $user?->id,
            'department_id' => $unit->id,
            'first_name' => 'PHPUnit',
            'last_name' => $label,
            'is_active' => 1,
        ]);
        $employee->employee_id = 'PHPUNIT-'.Str::upper(Str::random(8));
        $employee->save();

        return $employee;
    }

    /** A plain staff account: the "Employee" role (create_mission), no org responsibilities. */
    private function staffUser(): User
    {
        $user = User::create([
            'full_name' => 'PHPUnit Requester',
            'email' => 'phpunit_'.Str::lower(Str::random(10)).'@example.test',
            'password' => bcrypt('password'),
            'user_type_id' => 3,
        ]);
        $user->assignRole('Employee');

        return $user;
    }

    private function payload(array $employeeIds): array
    {
        return [
            'title' => 'PHPUnit training request',
            'destination' => 'Kampot',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'purpose' => 'PHPUnit purpose',
            'employee_ids' => $employeeIds,
        ];
    }

    public function test_staff_can_request_for_self_and_own_unit_colleagues_only(): void
    {
        $user = $this->staffUser();
        $self = $this->employee($this->office, 'Self', $user);
        $colleague = $this->employee($this->office, 'Colleague');
        $stranger = $this->employee($this->otherOffice, 'Stranger');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/missions/request-options')->assertOk()
            ->assertJsonPath('response.data.requester.employee_id', $self->id)
            ->assertJsonPath('response.data.requester.name', $self->full_name);
        $ids = collect($this->getJson('/api/v1/missions/colleagues?q=PHPUnit')->assertOk()->json('response.data'))->pluck('id');
        $this->assertTrue($ids->contains($colleague->id));
        $this->assertFalse($ids->contains($self->id));
        $this->assertFalse($ids->contains($stranger->id));

        $this->postJson('/api/v1/missions/request', $this->payload([$self->id, $stranger->id]))->assertForbidden();
        $id = $this->postJson('/api/v1/missions/request', $this->payload([$self->id, $colleague->id]) + [
            'transport_type_id' => (int) DB::table('transport_types')->value('id'),
            'sponsor_name' => 'US-CDC',
        ])->assertCreated()
            ->assertJsonPath('response.data.status', 'pending')
            ->assertJsonPath('response.data.assignments_count', 2)
            ->json('response.data.id');

        $mission = Mission::findOrFail($id);
        $this->assertSame(MissionCreationPath::EmployeeRequest, $mission->creation_path);
        $this->assertSame($self->id, (int) $mission->requester_employee_id);
        $this->assertNotNull($mission->workflow_instance_id);
        $this->assertSame('US-CDC', $mission->fundingLabel());
        // It shows up in the requester's own mission list, at its workflow stage.
        $this->getJson("/api/v1/missions/$id")->assertOk()->assertJsonPath('response.data.lifecycle_label', $mission->lifecycle_status->label());
    }

    public function test_account_without_employee_record_cannot_request(): void
    {
        Sanctum::actingAs($this->staffUser());
        $this->getJson('/api/v1/missions/request-options')->assertStatus(422);
        $this->postJson('/api/v1/missions/request', $this->payload([1]))->assertStatus(422);
    }
}
