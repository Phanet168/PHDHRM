<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('org_unit_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('name_km')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('org_unit_type_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_type_id')->constrained('org_unit_types')->cascadeOnDelete();
            $table->foreignId('child_type_id')->constrained('org_unit_types')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['parent_type_id', 'child_type_id'], 'org_unit_type_rules_parent_child_unique');
        });

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
                'name' => 'Bureau / Section',
                'name_km' => 'ផ្នែក / ការិយាល័យជំនាញ',
                'sort_order' => 30,
            ],
            [
                'code' => 'operational_district',
                'name' => 'Operational District',
                'name_km' => 'ស្រុកប្រតិបត្តិ',
                'sort_order' => 40,
            ],
            [
                'code' => 'district_hospital',
                'name' => 'District Referral Hospital',
                'name_km' => 'មន្ទីរពេទ្យបង្អែកស្រុក',
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
                'code' => 'health_post',
                'name' => 'Health Post',
                'name_km' => 'ប៉ុស្តិ៍សុខភាព',
                'sort_order' => 80,
            ],
        ];

        foreach ($types as $type) {
            DB::table('org_unit_types')->insert([
                'code' => $type['code'],
                'name' => $type['name'],
                'name_km' => $type['name_km'],
                'is_active' => true,
                'sort_order' => $type['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $typeIdByCode = DB::table('org_unit_types')->pluck('id', 'code')->toArray();

        $rules = [
            ['parent' => 'phd', 'child' => 'office'],
            ['parent' => 'phd', 'child' => 'bureau'],
            ['parent' => 'phd', 'child' => 'operational_district'],
            ['parent' => 'phd', 'child' => 'provincial_hospital'],
            ['parent' => 'office', 'child' => 'bureau'],
            ['parent' => 'operational_district', 'child' => 'district_hospital'],
            ['parent' => 'operational_district', 'child' => 'health_center'],
            ['parent' => 'district_hospital', 'child' => 'bureau'],
            ['parent' => 'provincial_hospital', 'child' => 'bureau'],
            ['parent' => 'health_center', 'child' => 'health_post'],
        ];

        foreach ($rules as $rule) {
            $parentTypeId = $typeIdByCode[$rule['parent']] ?? null;
            $childTypeId = $typeIdByCode[$rule['child']] ?? null;

            if (!$parentTypeId || !$childTypeId) {
                continue;
            }

            DB::table('org_unit_type_rules')->insert([
                'parent_type_id' => $parentTypeId,
                'child_type_id' => $childTypeId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_unit_type_rules');
        Schema::dropIfExists('org_unit_types');
    }
};
