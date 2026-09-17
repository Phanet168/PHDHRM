<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Shift;
use Modules\HumanResource\Services\ShiftResolverService;
use Modules\HumanResource\Support\AttendanceUnitScope;
use Modules\HumanResource\Support\OrgHierarchyAccessService;
use Tests\Support\BuildsAttendanceDatabase;
use Tests\TestCase;

class AttendanceUnitHierarchyTest extends TestCase
{
    use BuildsAttendanceDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceDatabase();
        $user = new User;
        $user->forceFill(['id' => 99, 'user_type_id' => 1]);
        \Laravel\Sanctum\Sanctum::actingAs($user);
        $this->scopeAccess(null);
        DB::table('org_unit_types')->insert([
            ['id' => 2, 'code' => 'phd'], ['id' => 3, 'code' => 'office'],
            ['id' => 4, 'code' => 'bureau'], ['id' => 5, 'code' => 'health_post'],
            ['id' => 6, 'code' => 'district_hospital'], ['id' => 7, 'code' => 'provincial_hospital'],
            ['id' => 8, 'code' => 'operational_district'], ['id' => 9, 'code' => 'health_center_with_bed'],
            ['id' => 10, 'code' => 'health_center_without_bed'],
        ]);
        DB::table('departments')->where('id', 1)->update(['unit_type_id' => 2]);
        DB::table('departments')->where('id', 2)->update(['parent_id' => 1]);
        DB::table('departments')->insert([
            ['id' => 3, 'department_name' => 'Office', 'unit_type_id' => 3, 'parent_id' => 1],
            ['id' => 4, 'department_name' => 'Section', 'unit_type_id' => 4, 'parent_id' => 3],
            ['id' => 5, 'department_name' => 'Post', 'unit_type_id' => 5, 'parent_id' => 2],
            ['id' => 6, 'department_name' => 'District hospital', 'unit_type_id' => 6, 'parent_id' => 1],
            ['id' => 7, 'department_name' => 'Provincial hospital', 'unit_type_id' => 7, 'parent_id' => 1],
            ['id' => 8, 'department_name' => 'OD', 'unit_type_id' => 8, 'parent_id' => 1],
            ['id' => 9, 'department_name' => 'HC with bed', 'unit_type_id' => 9, 'parent_id' => 8],
            ['id' => 10, 'department_name' => 'HC without bed', 'unit_type_id' => 10, 'parent_id' => 8],
        ]);
        foreach ([3, 4, 5, 6] as $id) {
            DB::table('employees')->insert(['id' => $id, 'department_id' => 1, 'sub_department_id' => $id]);
        }
    }

    private function scopeAccess(?array $ids): void
    {
        $access = \Mockery::mock(OrgHierarchyAccessService::class);
        $access->shouldReceive('managedBranchIds')->andReturn($ids);
        $access->shouldReceive('isSystemAdmin')->andReturn(true);
        $this->app->instance(OrgHierarchyAccessService::class, $access);
    }

    public function test_unit_list_includes_only_requested_types(): void
    {
        $response = $this->getJson('/api/v1/attendance/units')->assertOk();
        $this->assertSame([1, 7, 8, 2, 9, 10, 5], array_column($response->json('response.data'), 'id'));
        $this->getJson('/api/v1/attendance/dashboard?department_id=3')->assertNotFound();
    }

    public function test_internal_staff_belong_to_parent_but_child_facilities_remain_separate(): void
    {
        $scope = app(AttendanceUnitScope::class);
        $this->assertEqualsCanonicalizing([1, 3, 4], $scope->employees(1)->pluck('id')->all());
        $this->assertSame([2], $scope->employees(2)->pluck('id')->all());
        $this->assertSame([5], $scope->employees(5)->pluck('id')->all());
        $this->assertSame(1, AttendanceUnitScope::employeeUnit(Employee::findOrFail(4)));
        $this->assertSame(5, AttendanceUnitScope::employeeUnit(Employee::findOrFail(5)));
        $this->assertSame(0, AttendanceUnitScope::employeeUnit(Employee::findOrFail(6)));
        $this->getJson('/api/v1/attendance/dashboard?department_id=1')->assertOk()
            ->assertJsonPath('response.data.summary.total', 3);
    }

    public function test_internal_staff_use_parent_schedule(): void
    {
        $shift = Shift::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Headquarters', 'department_id' => 1, 'is_default' => true,
            'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
        $this->assertSame($shift->id, app(ShiftResolverService::class)->resolveForDate(4, Carbon::today())['shift']->id);
        $this->assertNull(app(ShiftResolverService::class)->resolveForDate(2, Carbon::today()));
    }

    public function test_branch_permissions_are_not_expanded_to_sibling_offices(): void
    {
        $this->scopeAccess([1, 3]);
        $scope = app(AttendanceUnitScope::class);
        $this->assertEqualsCanonicalizing([1, 3], $scope->employees(1)->pluck('id')->all());
        $this->assertSame([], $scope->employees(2)->pluck('id')->all());
        $this->scopeAccess([]);
        $this->assertSame(0, app(AttendanceUnitScope::class)->departments()->count());
        $this->assertSame(0, app(AttendanceUnitScope::class)->employees()->count());
    }

    public function test_invalid_internal_hierarchy_cannot_leak_into_parent_unit(): void
    {
        DB::table('departments')->where('id', 3)->update(['parent_id' => 4]);
        $this->assertSame(0, AttendanceUnitScope::employeeUnit(Employee::findOrFail(4)));
        $this->assertSame([1], app(AttendanceUnitScope::class)->employees(1)->pluck('id')->all());
    }

    public function test_qr_unit_options_exclude_internal_sections(): void
    {
        $rules = \Mockery::mock(\Modules\HumanResource\Support\OrgUnitRuleService::class);
        $rules->shouldReceive('hierarchyOptions')->andReturn(DB::table('departments')->get());
        $view = app(\Modules\HumanResource\Http\Controllers\ManualAttendanceController::class)->qrCreate(
            \Illuminate\Http\Request::create('/'), $rules,
            \Mockery::mock(\Modules\HumanResource\Support\OrgScopeService::class)
        );
        $this->assertSame([1, 7, 8, 2, 9, 10, 5], $view->getData()['orgUnitOptions']->pluck('id')->all());
    }
}
