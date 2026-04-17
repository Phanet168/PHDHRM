<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('employee_statuses')) {
            return;
        }

        $candidates = collect([
            'កំពុងបម្រើការងារ',
            'ចូលបម្រើការងារវិញ',
            'ទំនេរគ្មានបៀវត្ស',
            'ផ្អាកការងារ',
            'ផ្លាស់ចេញក្រៅអង្គភាព',
            'លាឈប់ពីការងារ',
            'ចូលនិវត្តន៍',
            'មរណភាព',
        ]);

        if (Schema::hasTable('employees')) {
            $employeeStatuses = DB::table('employees')
                ->whereNotNull('work_status_name')
                ->where('work_status_name', '<>', '')
                ->pluck('work_status_name');

            $candidates = $candidates->merge($employeeStatuses);
        }

        if (Schema::hasTable('employee_work_histories')) {
            $historyStatuses = DB::table('employee_work_histories')
                ->whereNotNull('work_status_name')
                ->where('work_status_name', '<>', '')
                ->pluck('work_status_name');

            $candidates = $candidates->merge($historyStatuses);
        }

        $existingByName = DB::table('employee_statuses')
            ->pluck('id', 'name_en')
            ->keys()
            ->map(fn ($v) => mb_strtolower(trim((string) $v)))
            ->filter()
            ->all();

        $existingByNameMap = array_fill_keys($existingByName, true);

        $sort = (int) DB::table('employee_statuses')->max('sort_order');
        $sort = $sort > 0 ? $sort : 0;

        foreach ($candidates as $value) {
            $name = trim((string) $value);
            if ($name === '') {
                continue;
            }

            $normalized = mb_strtolower($name);
            if (isset($existingByNameMap[$normalized])) {
                continue;
            }

            $sort++;
            DB::table('employee_statuses')->insert([
                'uuid' => (string) Str::uuid(),
                'code' => null,
                'name_km' => preg_match('/\p{Khmer}/u', $name) ? $name : null,
                'name_en' => $name,
                'sort_order' => $sort,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $existingByNameMap[$normalized] = true;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep seeded status records on rollback to avoid data loss.
    }
};
