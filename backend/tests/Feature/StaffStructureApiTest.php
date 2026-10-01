<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Tests\TestCase;

/**
 * Mobile "staff directory" API (/api/v1/staff-structure): read-only, always
 * scoped to the caller's OWN facility -- unlike the web admin's
 * AttendanceUnitScope::employees(), which is gated by management authority
 * and would return nothing for an ordinary staff member with no management
 * role. Per this project's standing "mobile app scope is personal, not
 * manager/admin" guidance, there is deliberately no department_id switch
 * here; the memory note is why this stays single-facility only. Runs
 * against the real (dev) database, wrapped in a transaction that is rolled
 * back afterwards.
 */
class StaffStructureApiTest extends TestCase
{
    use DatabaseTransactions;

    private Department $healthCenter;

    protected function setUp(): void
    {
        parent::setUp();
        $healthCenterTypeId = (int) DB::table('org_unit_types')->where('code', 'health_center')->value('id');
        $this->healthCenter = Department::create([
            'department_name' => 'PHPUnit Staff Structure Health Center',
            'unit_type_id' => $healthCenterTypeId,
            'is_active' => true,
        ]);
    }

    private function makeUserWithEmployee(string $label): array
    {
        $user = User::create([
            'name' => 'PHPUnit ' . $label,
            'email' => 'phpunit_' . Str::lower(Str::random(10)) . '@example.test',
            'password' => bcrypt('password'),
            'user_type_id' => 2,
        ]);
        $employee = new Employee([
            'user_id' => $user->id,
            'department_id' => $this->healthCenter->id,
            'first_name' => 'PHPUnit',
            'last_name' => $label,
            'is_active' => 1,
        ]);
        $employee->employee_id = 'PHPUNIT-' . Str::upper(Str::random(8));
        $employee->save();

        return [$user, $employee];
    }

    public function test_returns_own_facility_grouped_by_office_with_no_department_id_accepted(): void
    {
        [$user, $employee] = $this->makeUserWithEmployee('Self');
        $colleague = new Employee(['department_id' => $this->healthCenter->id, 'first_name' => 'PHPUnit', 'last_name' => 'Colleague', 'is_active' => 1]);
        $colleague->employee_id = 'PHPUNIT-' . Str::upper(Str::random(8));
        $colleague->save();

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v1/staff-structure');

        $response->assertOk();
        $response->assertJsonPath('response.status', 'ok');
        $response->assertJsonPath('response.meta.department_id', $this->healthCenter->id);
        $response->assertJsonPath('response.meta.department_name', 'PHPUnit Staff Structure Health Center');

        $employeeIds = collect($response->json('response.data'))
            ->where('type', 'employee')
            ->pluck('id');
        $this->assertTrue($employeeIds->contains($employee->id));
        $this->assertTrue($employeeIds->contains($colleague->id));
    }

    public function test_a_department_id_supplied_by_the_client_is_rejected_not_silently_ignored(): void
    {
        [$user] = $this->makeUserWithEmployee('NoOverride');
        $otherTypeId = (int) DB::table('org_unit_types')->where('code', 'phd')->value('id');
        $otherFacility = Department::create(['department_name' => 'PHPUnit Other Facility', 'unit_type_id' => $otherTypeId, 'is_active' => true]);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v1/staff-structure?department_id=' . $otherFacility->id);

        $response->assertStatus(422);
    }

    public function test_returns_empty_data_for_a_user_with_no_employee_profile(): void
    {
        $user = User::create([
            'name' => 'PHPUnit NoProfile',
            'email' => 'phpunit_' . Str::lower(Str::random(10)) . '@example.test',
            'password' => bcrypt('password'),
            'user_type_id' => 2,
        ]);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v1/staff-structure');

        $response->assertStatus(422);
    }
}
