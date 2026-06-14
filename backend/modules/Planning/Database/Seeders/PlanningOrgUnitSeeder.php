<?php

namespace Modules\Planning\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\HumanResource\Entities\Department;
use Modules\Planning\Entities\OrgUnit;

class PlanningOrgUnitSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::withoutGlobalScopes()
            ->with('unitType:id,code')
            ->select('id', 'department_name', 'parent_id', 'unit_type_id', 'is_active')
            ->orderBy('id')
            ->get();

        $mapped = [];

        foreach ($departments as $department) {
            $orgUnit = OrgUnit::updateOrCreate(
                ['source_department_id' => $department->id],
                [
                    'code' => 'OU-' . $department->id,
                    'name' => (string) $department->department_name,
                    'unit_type' => $this->mapUnitType((string) optional($department->unitType)->code),
                    'level' => 1,
                    'hierarchy_path' => null,
                    'is_active' => (bool) $department->is_active,
                ]
            );

            $mapped[$department->id] = $orgUnit;
        }

        foreach ($departments as $department) {
            $orgUnit = $mapped[$department->id] ?? null;

            if (!$orgUnit) {
                continue;
            }

            $parentOrgUnit = !empty($department->parent_id) ? ($mapped[$department->parent_id] ?? null) : null;

            $orgUnit->forceFill([
                'parent_id' => $parentOrgUnit?->id,
                'level' => $parentOrgUnit ? $parentOrgUnit->level + 1 : 1,
            ])->save();
        }

        OrgUnit::query()->with('parent')->get()->each(function (OrgUnit $orgUnit) {
            $segments = [$orgUnit->id];
            $cursor = $orgUnit->parent;

            while ($cursor) {
                array_unshift($segments, $cursor->id);
                $cursor = $cursor->parent;
            }

            $orgUnit->forceFill([
                'hierarchy_path' => implode('/', $segments),
            ])->save();
        });
    }

    private function mapUnitType(string $code): string
    {
        return match ($code) {
            'phd', 'provincial_health_department' => 'provincial_health_department',
            'phd_office' => 'phd_office',
            'od', 'operational_district' => 'operational_district',
            'od_office' => 'od_office',
            'provincial_hospital' => 'provincial_hospital',
            'referral_hospital' => 'referral_hospital',
            'health_center' => 'health_center',
            default => 'other',
        };
    }
}
