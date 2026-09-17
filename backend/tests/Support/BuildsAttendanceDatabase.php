<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait BuildsAttendanceDatabase
{
    protected function createAttendanceDatabase(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('org_unit_types', function (Blueprint $t) {
            $t->id();
            $t->string('code');
        });
        DB::table('org_unit_types')->insert(['id' => 1, 'code' => 'health_center']);
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('department_name');
            $t->integer('unit_type_id')->default(1);
            $t->integer('parent_id')->nullable();
            $t->softDeletes();
        });
        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->integer('department_id');
            $t->integer('sub_department_id')->nullable();
            $t->integer('default_shift_id')->nullable();
            $t->integer('is_active')->default(1);
            $t->string('first_name')->default('Test');
            $t->string('last_name')->default('Officer');
            $t->string('middle_name')->nullable();
            $t->string('employee_id')->nullable();
            $t->softDeletes();
        });
        $migration = require base_path('modules/HumanResource/Database/Migrations/2026_04_19_100000_create_shift_and_mission_tables.php');
        $migration->up();
        (require base_path('modules/HumanResource/Database/Migrations/2026_09_12_090000_add_unit_attendance_schedules.php'))->up();
        Schema::create('attendances', function (Blueprint $t) {
            $t->id();
            $t->integer('employee_id');
            $t->dateTime('time');
            $t->integer('machine_state');
            $t->integer('machine_id')->nullable();
            $t->integer('workplace_id')->nullable();
            $t->string('attendance_source')->nullable();
            $t->string('source_reference')->nullable();
            $t->string('scan_latitude')->nullable();
            $t->string('scan_longitude')->nullable();
            $t->boolean('exception_flag')->default(false);
            $t->string('exception_reason')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('attendance_daily_snapshots', function (Blueprint $t) {
            $t->id();
            $t->integer('employee_id');
            $t->date('snapshot_date');
            $t->integer('shift_id')->nullable();
            $t->string('attendance_status')->nullable();
            $t->dateTime('in_time')->nullable();
            $t->dateTime('out_time')->nullable();
            foreach (['worked_minutes', 'late_minutes', 'early_leave_minutes', 'leave_id', 'mission_id'] as $field) {
                $t->integer($field)->nullable();
            }
            $t->boolean('is_holiday')->default(false);
            $t->boolean('is_day_off')->default(false);
            $t->text('policy_payload')->nullable();
            $t->dateTime('computed_at')->nullable();
            $t->timestamps();
            $t->unique(['employee_id', 'snapshot_date']);
        });
        Schema::create('holidays', function (Blueprint $t) {
            $t->id();
            $t->date('start_date');
            $t->date('end_date');
            $t->softDeletes();
        });
        Schema::create('week_holidays', function (Blueprint $t) {
            $t->id();
            $t->string('dayname');
        });
        Schema::create('apply_leaves', function (Blueprint $t) {
            $t->id();
            $t->integer('employee_id');
            $t->boolean('is_approved');
            $t->date('leave_approved_start_date');
            $t->date('leave_approved_end_date');
            $t->softDeletes();
        });
        DB::table('departments')->insert([['id' => 1, 'department_name' => 'A'], ['id' => 2, 'department_name' => 'B']]);
        DB::table('employees')->insert([
            ['id' => 1, 'department_id' => 1, 'sub_department_id' => null],
            ['id' => 2, 'department_id' => 1, 'sub_department_id' => 2],
        ]);
    }
}
