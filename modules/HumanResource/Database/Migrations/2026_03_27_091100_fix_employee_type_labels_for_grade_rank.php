<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_types')) {
            return;
        }

        $hasName = Schema::hasColumn('employee_types', 'name');
        $hasEmployeeTypeName = Schema::hasColumn('employee_types', 'employee_type_name');
        $hasNameKm = Schema::hasColumn('employee_types', 'name_km');
        $hasIsActive = Schema::hasColumn('employee_types', 'is_active');
        $hasCreatedAt = Schema::hasColumn('employee_types', 'created_at');
        $hasUpdatedAt = Schema::hasColumn('employee_types', 'updated_at');
        $hasDetails = Schema::hasColumn('employee_types', 'details');

        $rows = DB::table('employee_types')->get();
        if ($rows->isEmpty()) {
            return;
        }

        $targets = [
            'state_cadre' => [
                'km' => 'បុគ្គលិកក្របខណ្ឌរដ្ឋ',
                'aliases' => ['full time', 'state cadre', 'civil servant', 'civil service', 'permanent', 'បុគ្គលិកក្របខណ្ឌរដ្ឋ', 'បុគ្គលិកក្របខ័ណ្ឌរដ្ឋ'],
                'details' => 'State Cadre',
            ],
            'contract' => [
                'km' => 'បុគ្គលិកកិច្ចសន្យា',
                'aliases' => ['contract', 'contractual', 'បុគ្គលិកកិច្ចសន្យា', 'កិច្ចសន្យា'],
                'details' => 'Contract Employee',
            ],
            'agreement' => [
                'km' => 'កិច្ចព្រមព្រៀង',
                'aliases' => ['agreement', 'mou', 'បុគ្គលិកកិច្ចព្រមព្រៀង', 'កិច្ចព្រមព្រៀង'],
                'details' => 'Agreement Employee',
            ],
        ];

        $matchedIdByCategory = [
            'state_cadre' => null,
            'contract' => null,
            'agreement' => null,
        ];

        foreach ($rows as $row) {
            $labels = array_filter([
                (string) data_get($row, 'employee_type_name', ''),
                (string) data_get($row, 'name_km', ''),
                (string) data_get($row, 'name', ''),
            ]);

            foreach ($targets as $category => $config) {
                if ($matchedIdByCategory[$category] !== null) {
                    continue;
                }

                foreach ($labels as $label) {
                    $labelNorm = mb_strtolower(trim((string) $label), 'UTF-8');
                    foreach ($config['aliases'] as $alias) {
                        $aliasNorm = mb_strtolower(trim((string) $alias), 'UTF-8');
                        if ($aliasNorm !== '' && str_contains($labelNorm, $aliasNorm)) {
                            $matchedIdByCategory[$category] = (int) $row->id;
                            break 3;
                        }
                    }
                }
            }
        }

        foreach ($targets as $category => $config) {
            $matchedId = $matchedIdByCategory[$category];
            if ($matchedId !== null && $matchedId > 0) {
                $update = [];
                if ($hasName) {
                    $update['name'] = $config['km'];
                }
                if ($hasEmployeeTypeName) {
                    $update['employee_type_name'] = $config['km'];
                }
                if ($hasNameKm) {
                    $update['name_km'] = $config['km'];
                }
                if ($hasDetails) {
                    $update['details'] = $config['details'];
                }
                if ($hasIsActive) {
                    $update['is_active'] = 1;
                }
                if ($hasUpdatedAt) {
                    $update['updated_at'] = now();
                }

                DB::table('employee_types')->where('id', $matchedId)->update($update);
                continue;
            }

            $insert = ['uuid' => (string) Str::uuid()];
            if ($hasName) {
                $insert['name'] = $config['km'];
            }
            if ($hasEmployeeTypeName) {
                $insert['employee_type_name'] = $config['km'];
            }
            if ($hasNameKm) {
                $insert['name_km'] = $config['km'];
            }
            if ($hasDetails) {
                $insert['details'] = $config['details'];
            }
            if ($hasIsActive) {
                $insert['is_active'] = 1;
            }
            if ($hasCreatedAt) {
                $insert['created_at'] = now();
            }
            if ($hasUpdatedAt) {
                $insert['updated_at'] = now();
            }

            DB::table('employee_types')->insert($insert);
        }
    }

    public function down(): void
    {
        // Keep synced master data; no destructive rollback.
    }
};

