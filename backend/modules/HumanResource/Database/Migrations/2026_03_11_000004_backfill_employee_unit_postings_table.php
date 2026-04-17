<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        DB::table('employees')
            ->select('id', 'department_id', 'sub_department_id', 'position_id', 'joining_date')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk(200, function ($employees) use ($now) {
                foreach ($employees as $employee) {
                    $departmentId = $employee->sub_department_id ?: $employee->department_id;

                    if (!$departmentId) {
                        continue;
                    }

                    $alreadyHasPrimaryPosting = DB::table('employee_unit_postings')
                        ->where('employee_id', $employee->id)
                        ->where('is_primary', true)
                        ->whereNull('end_date')
                        ->exists();

                    if ($alreadyHasPrimaryPosting) {
                        continue;
                    }

                    DB::table('employee_unit_postings')->insert([
                        'employee_id' => $employee->id,
                        'department_id' => $departmentId,
                        'position_id' => $employee->position_id,
                        'start_date' => $employee->joining_date,
                        'end_date' => null,
                        'is_primary' => true,
                        'note' => 'Backfilled from employee profile',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('employee_unit_postings')
            ->where('note', 'Backfilled from employee profile')
            ->delete();
    }
};
