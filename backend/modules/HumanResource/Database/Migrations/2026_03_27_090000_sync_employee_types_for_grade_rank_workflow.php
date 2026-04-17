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
        $hasDetails = Schema::hasColumn('employee_types', 'details');
        $hasIsActive = Schema::hasColumn('employee_types', 'is_active');
        $hasCreatedAt = Schema::hasColumn('employee_types', 'created_at');
        $hasUpdatedAt = Schema::hasColumn('employee_types', 'updated_at');

        if (!$hasName && !$hasEmployeeTypeName && !$hasNameKm) {
            return;
        }

        $targets = [
            [
                'category' => 'state_cadre',
                'km' => 'បុគ្គលិកក្របខណ្ឌរដ្ឋ',
                'fallback_en' => 'State Cadre',
                'aliases' => [
                    'បុគ្គលិកក្របខណ្ឌរដ្ឋ',
                    'បុគ្គលិកក្របខ័ណ្ឌរដ្ឋ',
                    'ក្របខណ្ឌរដ្ឋ',
                    'ក្របខ័ណ្ឌរដ្ឋ',
                    'មន្ត្រីរាជការ',
                    'មន្រ្តីរាជការ',
                    'state cadre',
                    'civil servant',
                    'civil service',
                    'full time',
                    'permanent',
                ],
            ],
            [
                'category' => 'contract',
                'km' => 'បុគ្គលិកកិច្ចសន្យា',
                'fallback_en' => 'Contract Employee',
                'aliases' => [
                    'បុគ្គលិកកិច្ចសន្យា',
                    'កិច្ចសន្យា',
                    'contract',
                    'contractual',
                ],
            ],
            [
                'category' => 'agreement',
                'km' => 'កិច្ចព្រមព្រៀង',
                'fallback_en' => 'Agreement Employee',
                'aliases' => [
                    'បុគ្គលិកកិច្ចព្រមព្រៀង',
                    'កិច្ចព្រមព្រៀង',
                    'agreement',
                    'mou',
                ],
            ],
        ];

        foreach ($targets as $target) {
            $matched = $this->firstMatch($target['aliases'], $hasName, $hasEmployeeTypeName, $hasNameKm);

            if ($matched) {
                $payload = [];
                if ($hasName) {
                    // Keep Khmer in `name` so all screens show consistent label.
                    $payload['name'] = $target['km'];
                }
                if ($hasEmployeeTypeName) {
                    $payload['employee_type_name'] = $target['km'];
                }
                if ($hasNameKm) {
                    $payload['name_km'] = $target['km'];
                }
                if ($hasDetails && empty((string) ($matched->details ?? ''))) {
                    $payload['details'] = $target['fallback_en'];
                }
                if ($hasIsActive) {
                    $payload['is_active'] = 1;
                }
                if ($hasUpdatedAt) {
                    $payload['updated_at'] = now();
                }

                if (!empty($payload)) {
                    DB::table('employee_types')->where('id', (int) $matched->id)->update($payload);
                }
                continue;
            }

            $insert = [
                'uuid' => (string) Str::uuid(),
            ];

            if ($hasName) {
                $insert['name'] = $target['km'];
            }
            if ($hasEmployeeTypeName) {
                $insert['employee_type_name'] = $target['km'];
            }
            if ($hasNameKm) {
                $insert['name_km'] = $target['km'];
            }
            if ($hasDetails) {
                $insert['details'] = $target['fallback_en'];
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
        // Intentionally no destructive rollback for master data sync.
    }

    protected function firstMatch(array $aliases, bool $hasName, bool $hasEmployeeTypeName, bool $hasNameKm): ?object
    {
        foreach ($aliases as $alias) {
            $alias = trim((string) $alias);
            if ($alias === '') {
                continue;
            }

            $query = DB::table('employee_types');
            $query->where(function ($q) use ($alias, $hasName, $hasEmployeeTypeName, $hasNameKm) {
                if ($hasName) {
                    $q->orWhereRaw('LOWER(name) = ?', [mb_strtolower($alias, 'UTF-8')]);
                }
                if ($hasEmployeeTypeName) {
                    $q->orWhereRaw('LOWER(employee_type_name) = ?', [mb_strtolower($alias, 'UTF-8')]);
                }
                if ($hasNameKm) {
                    $q->orWhereRaw('LOWER(name_km) = ?', [mb_strtolower($alias, 'UTF-8')]);
                }
            });

            $found = $query->orderBy('id')->first();
            if ($found) {
                return $found;
            }
        }

        return null;
    }
};

