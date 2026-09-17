<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\HumanResource\Entities\Shift;
use Modules\HumanResource\Entities\ShiftRoster;
use Modules\HumanResource\Services\QrAttendanceTokenService;
use Tests\Support\BuildsAttendanceDatabase;
use Tests\TestCase;

class MobileAttendanceApiTest extends TestCase
{
    use BuildsAttendanceDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceDatabase();
        config(['humanresource.attendance.require_qr_token' => false]);
        Carbon::setTestNow(Carbon::parse('2026-09-08 17:10:00'));
        Schema::table('employees', fn (Blueprint $t) => $t->integer('user_id')->nullable());
        Schema::table('departments', function (Blueprint $t) {
            foreach (['latitude', 'longitude', 'geofence_latitude', 'geofence_longitude', 'geofence_radius_meters'] as $field) {
                $t->decimal($field, 12, 7)->nullable();
            }
        });
        Schema::create('appsettings', function (Blueprint $t) {
            $t->id();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->integer('acceptablerange');
            $t->softDeletes();
        });
        (require base_path('database/migrations/2026_04_19_080000_create_attendance_scan_logs_table.php'))->up();
        (require base_path('modules/HumanResource/Database/Migrations/2026_09_12_100000_create_mobile_attendance_requests.php'))->up();
        DB::table('departments')->where('id', 1)->update(['latitude' => 11.55, 'longitude' => 104.92, 'geofence_radius_meters' => 100]);
        DB::table('employees')->where('id', 1)->update(['user_id' => 99]);
        $this->loginOfficer();
    }

    private function loginOfficer(int $id = 99): void
    {
        $user = new User;
        $user->forceFill(['id' => $id, 'user_type_id' => 3]);
        Sanctum::actingAs($user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function shift(array $data = []): Shift
    {
        return Shift::create($data + ['uuid' => (string) Str::uuid(), 'name' => 'Unit A', 'department_id' => 1,
            'start_time' => '08:00', 'morning_end_time' => '12:00', 'afternoon_start_time' => '14:00', 'end_time' => '17:00', 'is_active' => true, 'is_default' => true]);
    }

    private function scan(array $data = [])
    {
        return $this->postJson('/api/v1/attendance/scan', $data + ['latitude' => 11.55, 'longitude' => 104.92, 'request_id' => (string) Str::uuid()]);
    }

    public function test_history_requires_authentication(): void
    {
        auth()->forgetGuards();
        $this->getJson('/api/v1/attendance/history')->assertUnauthorized();
    }

    public function test_officer_can_read_own_schedule_without_management_permissions(): void
    {
        $this->shift();
        $this->getJson('/api/v1/attendance/schedule?from_date=2026-09-08&to_date=2026-09-09')
            ->assertOk()->assertJsonCount(2, 'response.data')->assertJsonPath('response.data.0.unit.id', 1)
            ->assertJsonPath('response.data.0.shift_source', 'unit_default')->assertJsonCount(2, 'response.data.0.sessions');
    }

    public function test_history_uses_session_minutes_and_excludes_another_officer(): void
    {
        $this->shift();
        foreach ([['08:00', 1], ['12:00', 2], ['14:20', 1], ['16:50', 2]] as [$time, $state]) {
            DB::table('attendances')->insert(['employee_id' => 1, 'time' => '2026-09-07 '.$time.':00', 'machine_state' => $state]);
        }
        DB::table('attendances')->insert(['employee_id' => 2, 'time' => '2026-09-07 08:00:00', 'machine_state' => 1]);
        $this->getJson('/api/v1/attendance/history?from_date=2026-09-07&to_date=2026-09-07')->assertOk()
            ->assertJsonPath('response.data.0.employee_id', 1)->assertJsonPath('response.data.0.worked_minutes', 390)
            ->assertJsonPath('response.data.0.attendance_status', 'late_and_early_leave')
            ->assertJsonPath('response.data.0.total_hours', '6:30:00')->assertJsonPath('response.data.0.punch_count', 4);
    }

    public function test_client_cannot_select_another_employee_or_backdate_a_scan(): void
    {
        $this->getJson('/api/v1/attendance/history?employee_id=2')->assertUnprocessable()->assertJsonValidationErrors('employee_id');
        $this->scan(['datetime' => '2020-01-01 08:00:00'])->assertUnprocessable()->assertJsonValidationErrors('datetime');
        $this->scan(['machine_state' => 2])->assertUnprocessable()->assertJsonValidationErrors('machine_state');
        $this->assertSame(0, DB::table('attendances')->count());
    }

    public function test_missing_or_inactive_employee_is_rejected(): void
    {
        $this->loginOfficer(123);
        $this->getJson('/api/v1/attendance/today')->assertUnprocessable();
        $this->loginOfficer();
        DB::table('employees')->where('id', 1)->update(['is_active' => 0]);
        $this->scan()->assertUnprocessable();
    }

    public function test_scan_is_saved_once_with_server_time_and_durable_retry_key(): void
    {
        $this->shift();
        $id = (string) Str::uuid();
        $this->scan(['request_id' => $id])->assertCreated()->assertJsonPath('response.punch_type', 'in')->assertJsonPath('response.duplicate', false);
        Carbon::setTestNow(now()->addMinutes(5));
        $this->scan(['request_id' => $id])->assertOk()->assertJsonPath('response.duplicate', true)->assertJsonPath('response.punch_type', 'in');
        $this->assertSame(1, DB::table('attendances')->count());
        $this->assertSame('2026-09-08 17:10:00', DB::table('attendances')->value('time'));
        $this->assertSame(2, DB::table('attendance_scan_logs')->count());
    }

    public function test_near_duplicate_gps_keeps_both_retry_keys_without_toggling(): void
    {
        $firstId = (string) Str::uuid();
        $secondId = (string) Str::uuid();
        $this->scan(['request_id' => $firstId])->assertCreated();
        Carbon::setTestNow(now()->addSeconds(20));
        $this->scan(['request_id' => $secondId])->assertOk()->assertJsonPath('response.duplicate', true);
        Carbon::setTestNow(now()->addMinutes(3));
        $this->scan(['request_id' => $secondId])->assertOk()->assertJsonPath('response.punch_type', 'in');
        $this->assertSame(1, DB::table('attendances')->count());
        $this->assertSame(2, DB::table('mobile_attendance_requests')->count());
    }

    public function test_geofence_and_foreign_qr_reject_capture(): void
    {
        $this->scan(['latitude' => 12.0])->assertUnprocessable()->assertJsonPath('response.error_code', 'out_of_range');
        $this->scan(['qr_token' => QrAttendanceTokenService::generate(['wid' => 2])])
            ->assertForbidden()->assertJsonPath('response.error_code', 'wrong_workplace');
        $this->scan(['qr_token' => 'bad-token'])->assertUnprocessable()->assertJsonPath('response.error_code', 'invalid_qr');
        $this->assertSame(0, DB::table('attendances')->count());
    }

    public function test_history_bounds_are_validated_and_future_days_are_not_absences(): void
    {
        $this->getJson('/api/v1/attendance/history?from_date=2026-01-01&to_date=2026-12-31')->assertUnprocessable();
        $this->getJson('/api/v1/attendance/history?from_date=bad')->assertUnprocessable();
        $this->getJson('/api/v1/attendance/history?from_date=2026-09-09&to_date=2026-09-10')->assertOk()->assertJsonCount(0, 'response.data');
    }

    public function test_next_action_resets_at_afternoon_session_and_keeps_overnight_work_date(): void
    {
        $this->shift();
        DB::table('attendances')->insert(['employee_id' => 1, 'time' => '2026-09-08 08:00:00', 'machine_state' => 1]);
        Carbon::setTestNow(Carbon::parse('2026-09-08 14:00:00'));
        $this->getJson('/api/v1/attendance/today')->assertOk()->assertJsonPath('response.meta.next_punch_type', 'in');
        $night = $this->shift(['is_default' => false, 'is_duty' => true, 'is_cross_day' => true, 'start_time' => '20:00', 'end_time' => '08:00', 'morning_end_time' => null, 'afternoon_start_time' => null]);
        ShiftRoster::create(['employee_id' => 1, 'roster_date' => '2026-09-08', 'shift_id' => $night->id, 'uuid' => (string) Str::uuid()]);
        DB::table('attendances')->where('employee_id', 1)->delete();
        DB::table('attendances')->insert(['employee_id' => 1, 'time' => '2026-09-08 20:00:00', 'machine_state' => 1]);
        Carbon::setTestNow(Carbon::parse('2026-09-09 07:55:00'));
        $this->getJson('/api/v1/attendance/today')->assertOk()->assertJsonPath('response.data.date', '2026-09-08')->assertJsonPath('response.meta.next_punch_type', 'out');
    }
}
