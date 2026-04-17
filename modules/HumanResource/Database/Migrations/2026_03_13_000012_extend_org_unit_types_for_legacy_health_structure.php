<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('org_unit_types')) {
            return;
        }

        $now = now();

        $types = [
            [
                'code' => 'phd',
                'name' => 'Provincial Health Department',
                'name_km' => 'មន្ទីរសុខាភិបាលខេត្ត',
                'sort_order' => 10,
            ],
            [
                'code' => 'office',
                'name' => 'Office',
                'name_km' => 'ការិយាល័យ',
                'sort_order' => 20,
            ],
            [
                'code' => 'bureau',
                'name' => 'Section',
                'name_km' => 'ផ្នែក',
                'sort_order' => 30,
            ],
            [
                'code' => 'program',
                'name' => 'Program',
                'name_km' => 'កម្មវិធី',
                'sort_order' => 35,
            ],
            [
                'code' => 'operational_district',
                'name' => 'Operational District',
                'name_km' => 'ស្រុកប្រតិបត្តិ',
                'sort_order' => 40,
            ],
            [
                'code' => 'od_section',
                'name' => 'OD Section',
                'name_km' => 'ផ្នែកក្នុងស្រុកប្រតិបត្តិ',
                'sort_order' => 45,
            ],
            [
                'code' => 'district_hospital',
                'name' => 'District Hospital',
                'name_km' => 'មន្ទីរពេទ្យស្រុក',
                'sort_order' => 50,
            ],
            [
                'code' => 'provincial_hospital',
                'name' => 'Provincial Hospital',
                'name_km' => 'មន្ទីរពេទ្យខេត្ត',
                'sort_order' => 60,
            ],
            [
                'code' => 'health_center',
                'name' => 'Health Center',
                'name_km' => 'មណ្ឌលសុខភាព',
                'sort_order' => 70,
            ],
            [
                'code' => 'health_center_with_bed',
                'name' => 'Health Center With Bed',
                'name_km' => 'មណ្ឌលសុខភាពមានគ្រែ',
                'sort_order' => 71,
            ],
            [
                'code' => 'health_center_without_bed',
                'name' => 'Health Center Without Bed',
                'name_km' => 'មណ្ឌលសុខភាពគ្មានគ្រែ',
                'sort_order' => 72,
            ],
            [
                'code' => 'health_post',
                'name' => 'Health Post',
                'name_km' => 'ប៉ុស្តិ៍សុខភាព',
                'sort_order' => 80,
            ],
        ];

        foreach ($types as $type) {
            $existing = DB::table('org_unit_types')->where('code', $type['code'])->first();

            if ($existing) {
                DB::table('org_unit_types')
                    ->where('id', $existing->id)
                    ->update([
                        'name' => $type['name'],
                        'name_km' => $type['name_km'],
                        'sort_order' => $type['sort_order'],
                        'is_active' => true,
                        'updated_at' => $now,
                    ]);
                continue;
            }

            DB::table('org_unit_types')->insert([
                'code' => $type['code'],
                'name' => $type['name'],
                'name_km' => $type['name_km'],
                'sort_order' => $type['sort_order'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!Schema::hasTable('org_unit_type_rules')) {
            return;
        }

        $typeIdByCode = DB::table('org_unit_types')->pluck('id', 'code')->toArray();

        $rules = [
            ['parent' => 'phd', 'child' => 'office'],
            ['parent' => 'office', 'child' => 'bureau'],
            ['parent' => 'bureau', 'child' => 'program'],
            ['parent' => 'phd', 'child' => 'operational_district'],
            ['parent' => 'operational_district', 'child' => 'od_section'],
            ['parent' => 'operational_district', 'child' => 'district_hospital'],
            ['parent' => 'phd', 'child' => 'provincial_hospital'],
            ['parent' => 'operational_district', 'child' => 'health_center'],
            ['parent' => 'operational_district', 'child' => 'health_center_with_bed'],
            ['parent' => 'operational_district', 'child' => 'health_center_without_bed'],
            ['parent' => 'od_section', 'child' => 'health_center'],
            ['parent' => 'od_section', 'child' => 'health_center_with_bed'],
            ['parent' => 'od_section', 'child' => 'health_center_without_bed'],
            ['parent' => 'health_center', 'child' => 'health_post'],
            ['parent' => 'health_center_with_bed', 'child' => 'health_post'],
            ['parent' => 'health_center_without_bed', 'child' => 'health_post'],
        ];

        foreach ($rules as $rule) {
            $parentTypeId = $typeIdByCode[$rule['parent']] ?? null;
            $childTypeId = $typeIdByCode[$rule['child']] ?? null;

            if (!$parentTypeId || !$childTypeId) {
                continue;
            }

            DB::table('org_unit_type_rules')->updateOrInsert(
                [
                    'parent_type_id' => $parentTypeId,
                    'child_type_id' => $childTypeId,
                ],
                [
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('org_unit_types')) {
            return;
        }

        DB::table('org_unit_types')
            ->whereIn('code', ['health_center_with_bed', 'health_center_without_bed'])
            ->delete();
    }
};

