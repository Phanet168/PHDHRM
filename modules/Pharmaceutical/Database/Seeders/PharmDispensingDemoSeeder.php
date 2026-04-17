<?php

namespace Modules\Pharmaceutical\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PharmDispensingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $userId = 1;

        // Hospital (17) and Health Centers (64-68)
        $hospitalId = 17;
        $hcIds = [64, 65, 66, 67, 68];

        $dispensings = [];
        $dispItems = [];
        $dispId = 0;

        // ── Hospital dispensings (last 7 days) ──
        $hospitalPatients = [
            ['name' => 'សុខ សុផាត',     'id_no' => 'HC-2026-0001', 'gender' => 'M', 'age' => 45, 'diagnosis' => 'ជម្ងឺលើសឈាម'],
            ['name' => 'ចាន់ សុភា',     'id_no' => 'HC-2026-0002', 'gender' => 'F', 'age' => 32, 'diagnosis' => 'គ្រុនក្តៅ និង ឈឺក្បាល'],
            ['name' => 'វង់ សុផល',       'id_no' => 'HC-2026-0003', 'gender' => 'M', 'age' => 58, 'diagnosis' => 'ជម្ងឺទឹកនោមផ្អែម ប្រភេទ២'],
            ['name' => 'សៀង សុខា',      'id_no' => 'HC-2026-0004', 'gender' => 'F', 'age' => 28, 'diagnosis' => 'រលាកផ្លូវដង្ហើម'],
            ['name' => 'ម៉ម ម៉ារីដា',    'id_no' => 'HC-2026-0005', 'gender' => 'F', 'age' => 22, 'diagnosis' => 'អាឡែស៊ី និង រមាស់'],
            ['name' => 'ប៉ែន បូរ៉ា',     'id_no' => 'HC-2026-0006', 'gender' => 'M', 'age' => 67, 'diagnosis' => 'ឈឺសន្លាក់ និង ដំបៅ'],
            ['name' => 'លី សុខេង',      'id_no' => 'HC-2026-0007', 'gender' => 'M', 'age' => 4,  'diagnosis' => 'រាកបន្ទោរ កុមារ'],
            ['name' => 'នុន សុវណ្ណា',    'id_no' => 'HC-2026-0008', 'gender' => 'F', 'age' => 35, 'diagnosis' => 'រលាកទងសួត'],
        ];

        $hospitalMeds = [
            // [medicine_id, qty, batch, dosage, days]
            [['med' => 1,  'qty' => 30,  'batch' => 'BN-2026-001', 'dosage' => '1គ្រាប់ x3ដង/ថ្ងៃ ក្រោយអាហារ',   'days' => 10]],
            [['med' => 6,  'qty' => 20,  'batch' => 'BN-2026-006', 'dosage' => '1គ្រាប់ x3ដង/ថ្ងៃ នៅពេលឈឺ',    'days' => 7],
             ['med' => 10, 'qty' => 10,  'batch' => 'BN-2026-010', 'dosage' => '1គ្រាប់ x2ដង/ថ្ងៃ',              'days' => 5]],
            [['med' => 15, 'qty' => 60,  'batch' => 'BN-2026-015', 'dosage' => '1គ្រាប់ x2ដង/ថ្ងៃ ក្រោយអាហារ',   'days' => 30]],
            [['med' => 1,  'qty' => 21,  'batch' => 'BN-2026-001', 'dosage' => '1គ្រាប់ x3ដង/ថ្ងៃ ក្រោយអាហារ',   'days' => 7],
             ['med' => 6,  'qty' => 15,  'batch' => 'BN-2026-006', 'dosage' => '1គ្រាប់ x3ដង/ថ្ងៃ ពេលឈឺ',       'days' => 5]],
            [['med' => 11, 'qty' => 10,  'batch' => 'BN-2026-011', 'dosage' => '1គ្រាប់ ១ដង/ថ្ងៃ ពេលព្រឹក',     'days' => 10]],
            [['med' => 8,  'qty' => 21,  'batch' => 'BN-2026-008', 'dosage' => '1គ្រាប់ x3ដង/ថ្ងៃ ក្រោយអាហារ',   'days' => 7],
             ['med' => 17, 'qty' => 20,  'batch' => 'BN-2026-017', 'dosage' => '1គ្រាប់/ថ្ងៃ',                     'days' => 20]],
            [['med' => 21, 'qty' => 6,   'batch' => 'BN-2026-021', 'dosage' => '1កញ្ចប់/ថ្ងៃ លាយទឹក 200ml',      'days' => 3],
             ['med' => 19, 'qty' => 10,  'batch' => 'BN-2026-019', 'dosage' => '1គ្រាប់/ថ្ងៃ រយៈពេល10ថ្ងៃ',       'days' => 10]],
            [['med' => 1,  'qty' => 15,  'batch' => 'BN-2026-001', 'dosage' => '1គ្រាប់ x3ដង/ថ្ងៃ ក្រោយអាហារ',   'days' => 5],
             ['med' => 6,  'qty' => 10,  'batch' => 'BN-2026-006', 'dosage' => '1គ្រាប់ x3ដង ពេលឈឺ',             'days' => 5]],
        ];

        foreach ($hospitalPatients as $i => $patient) {
            $dispId++;
            $date = Carbon::create(2026, 3, 28 + $i); // Mar 28 to Apr 4
            if ($date->gt(Carbon::create(2026, 4, 5))) $date = Carbon::create(2026, 4, 5);

            $dispensings[] = [
                'id'              => $dispId,
                'reference_no'    => 'DISP-' . $date->format('Ymd') . '-' . str_pad($dispId, 4, '0', STR_PAD_LEFT),
                'department_id'   => $hospitalId,
                'dispensing_date' => $date->format('Y-m-d'),
                'patient_name'    => $patient['name'],
                'patient_id_no'   => $patient['id_no'],
                'patient_gender'  => $patient['gender'],
                'patient_age'     => $patient['age'],
                'diagnosis'       => $patient['diagnosis'],
                'note'            => null,
                'dispensed_by'    => $userId,
                'created_by'      => $userId,
                'created_at'      => $date,
                'updated_at'      => $date,
            ];

            foreach ($hospitalMeds[$i] as $med) {
                $dispItems[] = [
                    'dispensing_id'      => $dispId,
                    'medicine_id'        => $med['med'],
                    'quantity'           => $med['qty'],
                    'batch_no'           => $med['batch'],
                    'dosage_instruction' => $med['dosage'],
                    'duration_days'      => $med['days'],
                    'note'               => null,
                    'created_at'         => $date,
                    'updated_at'         => $date,
                ];
            }
        }

        // ── Health Center dispensings (HC 64 & 65, each 3 patients) ──
        $hcPatients = [
            // HC 64 - ចំការលើ
            [64, 'ហួត ចំរើន',    'P-064-001', 'M', 38, 'គ្រុនក្តៅ',           [[6, 15, 'BN-2026-006', '1គ្រាប់ x3ដង/ថ្ងៃ', 5]]],
            [64, 'សំ សុខលី',     'P-064-002', 'F', 25, 'ផ្តាស់ និង ក្អក',    [[6, 10, 'BN-2026-006', '1គ្រាប់ x2ដង/ថ្ងៃ', 5], [10, 10, 'BN-2026-010', '1គ្រាប់ ព្រឹក និង ល្ងាច', 5]]],
            [64, 'កែវ សុខន',     'P-064-003', 'M', 3,  'រាកបន្ទោរ កុមារ',   [[21, 5, 'BN-2026-021', '1កញ្ចប់ក្នុង1ថ្ងៃ', 5], [19, 10, 'BN-2026-019', '1គ្រាប់/ថ្ងៃ', 10]]],
            // HC 65 - ស្រែគរ
            [65, 'ប៉ុន សុវណ្ណី',  'P-065-001', 'F', 42, 'ឈឺក្បាល រ៉ាំរ៉ៃ',   [[6, 20, 'BN-2026-006', '1គ្រាប់ x2ដង/ថ្ងៃ ពេលឈឺ', 10]]],
            [65, 'យឹម យ៉េង',     'P-065-002', 'M', 55, 'ឈឺពោះ រលាក',       [[4, 15, 'BN-2026-004', '1គ្រាប់ x3ដង/ថ្ងៃ ក្រោយអាហារ', 5]]],
            [65, 'អ៊ុង សុវណ្ណ',   'P-065-003', 'F', 30, 'ស្បែកក្រហម អាឡែស៊ី', [[10, 14, 'BN-2026-010', '1គ្រាប់ x2ដង/ថ្ងៃ', 7], [17, 14, 'BN-2026-017', '1គ្រាប់/ថ្ងៃ', 14]]],
        ];

        foreach ($hcPatients as $hp) {
            $dispId++;
            $date = Carbon::create(2026, 4, rand(1, 4));
            $dispensings[] = [
                'id'              => $dispId,
                'reference_no'    => 'DISP-' . $date->format('Ymd') . '-' . str_pad($dispId, 4, '0', STR_PAD_LEFT),
                'department_id'   => $hp[0],
                'dispensing_date' => $date->format('Y-m-d'),
                'patient_name'    => $hp[1],
                'patient_id_no'   => $hp[2],
                'patient_gender'  => $hp[3],
                'patient_age'     => $hp[4],
                'diagnosis'       => $hp[5],
                'note'            => null,
                'dispensed_by'    => $userId,
                'created_by'      => $userId,
                'created_at'      => $date,
                'updated_at'      => $date,
            ];

            foreach ($hp[6] as $med) {
                $dispItems[] = [
                    'dispensing_id'      => $dispId,
                    'medicine_id'        => $med[0],
                    'quantity'           => $med[1],
                    'batch_no'           => $med[2],
                    'dosage_instruction' => $med[3],
                    'duration_days'      => $med[4],
                    'note'               => null,
                    'created_at'         => $date,
                    'updated_at'         => $date,
                ];
            }
        }

        DB::table('pharm_dispensings')->insert($dispensings);
        DB::table('pharm_dispensing_items')->insert($dispItems);
    }
}
