<?php

namespace Modules\Pharmaceutical\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PharmaceuticalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $userId = 1; // default admin user

        // Department IDs from real data
        $phdId      = 15; // មន្ទីរសុខាភិបាលខេត្ត ស្ទឹងត្រែង
        $hospitalId = 17; // មន្ទីរពេទ្យបង្អែកខេត្ត
        $odId       = 18; // ការិយាល័យស្រុកប្រតិបត្តិស្ទឹងត្រែង
        $hcIds      = [64, 65, 66, 67, 68]; // Health centers

        // ─────────────────────────────────────────────
        // 1. Categories (ប្រភេទឱសថ)
        // ─────────────────────────────────────────────
        $categories = [
            ['id' => 1, 'name' => 'Antibiotics',       'name_kh' => 'អង់ទីប៊ីយោទិក',       'description' => 'ឱសថសម្រាប់ប្រឆាំងបាក់តេរី',       'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Analgesics',        'name_kh' => 'ឱសថបំបាត់ការឈឺ',      'description' => 'ឱសថសម្រាប់បន្ថយការឈឺចាប់',        'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Antipyretics',      'name_kh' => 'ឱសថបន្ថយគ្រុន',       'description' => 'ឱសថសម្រាប់បន្ថយសីតុណ្ហភាព',       'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Antihistamines',    'name_kh' => 'អង់ទីហ៊ីស្តាមីន',     'description' => 'ឱសថសម្រាប់ប្រឆាំងអាឡែស៊ី',       'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Antihypertensives', 'name_kh' => 'ឱសថបន្ថយសម្ពាធឈាម',  'description' => 'ឱសថសម្រាប់ព្យាបាលជម្ងឺលើសឈាម',   'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'Antidiabetics',     'name_kh' => 'ឱសថព្យាបាលទឹកនោមផ្អែម', 'description' => 'ឱសថសម្រាប់គ្រប់គ្រងជម្ងឺទឹកនោមផ្អែម', 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'name' => 'Vitamins & Supplements', 'name_kh' => 'វីតាមីន និងអាហារបំប៉ន', 'description' => 'វីតាមីន និងអាហារបំប៉នផ្សេងៗ', 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 8, 'name' => 'ORS & IV Fluids',   'name_kh' => 'អូអរអែស និងសេរ៉ូម',   'description' => 'ទឹកអំបិលផ្ទាល់មាត់ និងសេរ៉ូម',    'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
        ];
        DB::table('pharm_categories')->insert($categories);

        // ─────────────────────────────────────────────
        // 2. Medicines (មុខឱសថ)
        // ─────────────────────────────────────────────
        $medicines = [
            // Antibiotics
            ['id' => 1,  'category_id' => 1, 'code' => 'MED-001', 'name' => 'Amoxicillin 500mg',         'name_kh' => 'អាម៉ុកស៊ីស៊ីលីន ៥០០មក',   'dosage_form' => 'Capsule',  'strength' => '500mg',      'unit' => 'Capsule', 'manufacturer' => 'Medochemie', 'unit_price' => 0.15, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2,  'category_id' => 1, 'code' => 'MED-002', 'name' => 'Amoxicillin 250mg/5ml Syrup', 'name_kh' => 'អាម៉ុកស៊ីស៊ីលីន ស៊ីរ៉ូ',  'dosage_form' => 'Syrup',    'strength' => '250mg/5ml',  'unit' => 'Bottle',  'manufacturer' => 'Medochemie', 'unit_price' => 1.50, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3,  'category_id' => 1, 'code' => 'MED-003', 'name' => 'Ciprofloxacin 500mg',       'name_kh' => 'ស៊ីប្រូហ្វ្លុកសាស៊ីន ៥០០មក', 'dosage_form' => 'Tablet',   'strength' => '500mg',      'unit' => 'Tablet',  'manufacturer' => 'Cipla',      'unit_price' => 0.20, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4,  'category_id' => 1, 'code' => 'MED-004', 'name' => 'Metronidazole 250mg',       'name_kh' => 'មេត្រូនីដាហ្សូល ២៥០មក',    'dosage_form' => 'Tablet',   'strength' => '250mg',      'unit' => 'Tablet',  'manufacturer' => 'Sanofi',     'unit_price' => 0.08, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5,  'category_id' => 1, 'code' => 'MED-005', 'name' => 'Cotrimoxazole 480mg',       'name_kh' => 'កូទ្រីម៉ុកសាហ្សូល ៤៨០មក', 'dosage_form' => 'Tablet',   'strength' => '480mg',      'unit' => 'Tablet',  'manufacturer' => 'Roche',      'unit_price' => 0.10, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],

            // Analgesics / Antipyretics
            ['id' => 6,  'category_id' => 2, 'code' => 'MED-006', 'name' => 'Paracetamol 500mg',         'name_kh' => 'ប៉ារ៉ាសេតាម៉ូល ៥០០មក',   'dosage_form' => 'Tablet',   'strength' => '500mg',      'unit' => 'Tablet',  'manufacturer' => 'GSK',        'unit_price' => 0.05, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7,  'category_id' => 2, 'code' => 'MED-007', 'name' => 'Paracetamol 120mg/5ml Syrup', 'name_kh' => 'ប៉ារ៉ាសេតាម៉ូល ស៊ីរ៉ូ',  'dosage_form' => 'Syrup',    'strength' => '120mg/5ml',  'unit' => 'Bottle',  'manufacturer' => 'GSK',        'unit_price' => 1.20, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 8,  'category_id' => 2, 'code' => 'MED-008', 'name' => 'Ibuprofen 400mg',           'name_kh' => 'អ៊ីប៊ុយប្រូហ្វែន ៤០០មក',  'dosage_form' => 'Tablet',   'strength' => '400mg',      'unit' => 'Tablet',  'manufacturer' => 'Pfizer',     'unit_price' => 0.12, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9,  'category_id' => 3, 'code' => 'MED-009', 'name' => 'Aspirin 300mg',             'name_kh' => 'អាស្ពីរីន ៣០០មក',        'dosage_form' => 'Tablet',   'strength' => '300mg',      'unit' => 'Tablet',  'manufacturer' => 'Bayer',      'unit_price' => 0.06, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],

            // Antihistamines
            ['id' => 10, 'category_id' => 4, 'code' => 'MED-010', 'name' => 'Chlorpheniramine 4mg',      'name_kh' => 'ក្លរផេនីរ៉ាមីន ៤មក',     'dosage_form' => 'Tablet',   'strength' => '4mg',        'unit' => 'Tablet',  'manufacturer' => 'Medochemie', 'unit_price' => 0.04, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'category_id' => 4, 'code' => 'MED-011', 'name' => 'Loratadine 10mg',           'name_kh' => 'ឡូរ៉ាតាឌីន ១០មក',        'dosage_form' => 'Tablet',   'strength' => '10mg',       'unit' => 'Tablet',  'manufacturer' => 'Schering',   'unit_price' => 0.10, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],

            // Antihypertensives
            ['id' => 12, 'category_id' => 5, 'code' => 'MED-012', 'name' => 'Amlodipine 5mg',            'name_kh' => 'អាម៉្លូឌីពីន ៥មក',       'dosage_form' => 'Tablet',   'strength' => '5mg',        'unit' => 'Tablet',  'manufacturer' => 'Pfizer',     'unit_price' => 0.15, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 13, 'category_id' => 5, 'code' => 'MED-013', 'name' => 'Enalapril 10mg',            'name_kh' => 'អេណាឡាប្រីល ១០មក',       'dosage_form' => 'Tablet',   'strength' => '10mg',       'unit' => 'Tablet',  'manufacturer' => 'Merck',      'unit_price' => 0.18, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 14, 'category_id' => 5, 'code' => 'MED-014', 'name' => 'Losartan 50mg',             'name_kh' => 'ឡូសាតង់ ៥០មក',           'dosage_form' => 'Tablet',   'strength' => '50mg',       'unit' => 'Tablet',  'manufacturer' => 'Merck',      'unit_price' => 0.25, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],

            // Antidiabetics
            ['id' => 15, 'category_id' => 6, 'code' => 'MED-015', 'name' => 'Metformin 500mg',           'name_kh' => 'មេតហ្វ័រមីន ៥០០មក',       'dosage_form' => 'Tablet',   'strength' => '500mg',      'unit' => 'Tablet',  'manufacturer' => 'Merck',      'unit_price' => 0.08, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 16, 'category_id' => 6, 'code' => 'MED-016', 'name' => 'Glibenclamide 5mg',         'name_kh' => 'គ្លីបេនក្លាមីត ៥មក',     'dosage_form' => 'Tablet',   'strength' => '5mg',        'unit' => 'Tablet',  'manufacturer' => 'Sanofi',     'unit_price' => 0.10, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],

            // Vitamins & Supplements
            ['id' => 17, 'category_id' => 7, 'code' => 'MED-017', 'name' => 'Vitamin C 250mg',           'name_kh' => 'វីតាមីន C ២៥០មក',        'dosage_form' => 'Tablet',   'strength' => '250mg',      'unit' => 'Tablet',  'manufacturer' => 'Roche',      'unit_price' => 0.03, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 18, 'category_id' => 7, 'code' => 'MED-018', 'name' => 'Iron + Folic Acid',         'name_kh' => 'ដែក + អាស៊ីតហ្វូលិក',    'dosage_form' => 'Tablet',   'strength' => '60mg+0.4mg', 'unit' => 'Tablet',  'manufacturer' => 'UNICEF',     'unit_price' => 0.02, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 19, 'category_id' => 7, 'code' => 'MED-019', 'name' => 'Zinc 20mg',                 'name_kh' => 'ស័ង្កសី ២០មក',            'dosage_form' => 'Tablet',   'strength' => '20mg',       'unit' => 'Tablet',  'manufacturer' => 'UNICEF',     'unit_price' => 0.04, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 20, 'category_id' => 7, 'code' => 'MED-020', 'name' => 'Multivitamin',              'name_kh' => 'ពហុវីតាមីន',              'dosage_form' => 'Tablet',   'strength' => null,         'unit' => 'Tablet',  'manufacturer' => 'Roche',      'unit_price' => 0.05, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],

            // ORS & IV Fluids
            ['id' => 21, 'category_id' => 8, 'code' => 'MED-021', 'name' => 'ORS Sachets',               'name_kh' => 'ទឹកអំបិល ORS',            'dosage_form' => 'Powder',   'strength' => '20.5g',      'unit' => 'Sachet',  'manufacturer' => 'UNICEF',     'unit_price' => 0.30, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 22, 'category_id' => 8, 'code' => 'MED-022', 'name' => 'Normal Saline 0.9% 500ml',  'name_kh' => 'ទឹកអំបិល ០.៩% ៥០០មល',    'dosage_form' => 'IV Fluid', 'strength' => '0.9%',       'unit' => 'Bottle',  'manufacturer' => 'B.Braun',    'unit_price' => 2.50, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 23, 'category_id' => 8, 'code' => 'MED-023', 'name' => 'Ringer Lactate 500ml',      'name_kh' => 'រីងហ្គឺឡាក់តាត ៥០០មល',   'dosage_form' => 'IV Fluid', 'strength' => '500ml',      'unit' => 'Bottle',  'manufacturer' => 'B.Braun',    'unit_price' => 2.80, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 24, 'category_id' => 8, 'code' => 'MED-024', 'name' => 'Dextrose 5% 500ml',         'name_kh' => 'ដិចស្ត្រូស ៥% ៥០០មល',    'dosage_form' => 'IV Fluid', 'strength' => '5%',         'unit' => 'Bottle',  'manufacturer' => 'B.Braun',    'unit_price' => 2.20, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 25, 'category_id' => 1, 'code' => 'MED-025', 'name' => 'Doxycycline 100mg',         'name_kh' => 'ដុកស៊ីស៊ីក្លីន ១០០មក',   'dosage_form' => 'Capsule',  'strength' => '100mg',      'unit' => 'Capsule', 'manufacturer' => 'Pfizer',     'unit_price' => 0.12, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
        ];
        DB::table('pharm_medicines')->insert($medicines);

        // ─────────────────────────────────────────────
        // 3. Facility Stock (សន្និធិឱសថតាមគ្រឹះស្ថាន)
        // ─────────────────────────────────────────────
        $stockId = 0;
        $stocks = [];

        // PHD stock (large quantities)
        foreach ([1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25] as $medId) {
            $stockId++;
            $stocks[] = [
                'id'            => $stockId,
                'department_id' => $phdId,
                'medicine_id'   => $medId,
                'quantity'      => rand(5000, 20000),
                'batch_no'      => 'BN-2026-' . str_pad($medId, 3, '0', STR_PAD_LEFT),
                'expiry_date'   => Carbon::create(2027, rand(1, 12), 1)->format('Y-m-d'),
                'unit_price'    => $medicines[$medId - 1]['unit_price'],
                'updated_by'    => $userId,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        // Hospital stock (medium quantities)
        foreach ([1,3,6,7,8,9,12,13,17,18,21,22,23,24] as $medId) {
            $stockId++;
            $stocks[] = [
                'id'            => $stockId,
                'department_id' => $hospitalId,
                'medicine_id'   => $medId,
                'quantity'      => rand(500, 3000),
                'batch_no'      => 'BN-2026-' . str_pad($medId, 3, '0', STR_PAD_LEFT),
                'expiry_date'   => Carbon::create(2027, rand(1, 12), 1)->format('Y-m-d'),
                'unit_price'    => $medicines[$medId - 1]['unit_price'],
                'updated_by'    => $userId,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        // OD stock (medium quantities)
        foreach ([1,2,4,5,6,7,10,11,15,16,17,18,19,20,21] as $medId) {
            $stockId++;
            $stocks[] = [
                'id'            => $stockId,
                'department_id' => $odId,
                'medicine_id'   => $medId,
                'quantity'      => rand(800, 4000),
                'batch_no'      => 'BN-2026-' . str_pad($medId, 3, '0', STR_PAD_LEFT),
                'expiry_date'   => Carbon::create(2027, rand(3, 12), 1)->format('Y-m-d'),
                'unit_price'    => $medicines[$medId - 1]['unit_price'],
                'updated_by'    => $userId,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        // Health Center stocks (small quantities, some low stock for alerts)
        foreach ($hcIds as $hcId) {
            $hcMedicines = [1, 6, 7, 10, 17, 18, 19, 21]; // basic medicines
            foreach ($hcMedicines as $medId) {
                $stockId++;
                // Make some stocks low (≤ 10) for alert testing
                $qty = ($hcId === 64 && in_array($medId, [1, 6])) ? rand(3, 8) : rand(50, 500);
                $stocks[] = [
                    'id'            => $stockId,
                    'department_id' => $hcId,
                    'medicine_id'   => $medId,
                    'quantity'      => $qty,
                    'batch_no'      => 'BN-2026-' . str_pad($medId, 3, '0', STR_PAD_LEFT),
                    'expiry_date'   => ($hcId === 65 && $medId === 21)
                        ? Carbon::now()->addMonths(2)->format('Y-m-d') // expiring soon for alert
                        : Carbon::create(2027, rand(6, 12), 1)->format('Y-m-d'),
                    'unit_price'    => $medicines[$medId - 1]['unit_price'],
                    'updated_by'    => $userId,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }
        }

        DB::table('pharm_facility_stocks')->insert($stocks);

        // ─────────────────────────────────────────────
        // 4. Distributions (ការចែកចាយឱសថ)
        // ─────────────────────────────────────────────
        $distributions = [
            // PHD → Hospital (completed)
            [
                'id' => 1, 'reference_no' => 'DIST-20260301-0001',
                'distribution_type' => 'phd_to_hospital',
                'from_department_id' => $phdId, 'to_department_id' => $hospitalId,
                'distribution_date' => '2026-03-01', 'status' => 'completed',
                'note' => 'ការចែកចាយប្រចាំខែមីនា ២០២៦ ទៅមន្ទីរពេទ្យ',
                'received_date' => '2026-03-02', 'received_note' => 'បានទទួលគ្រប់ចំនួន',
                'sent_by' => $userId, 'received_by' => $userId,
                'created_by' => $userId, 'created_at' => Carbon::create(2026, 3, 1), 'updated_at' => Carbon::create(2026, 3, 2),
            ],
            // PHD → OD (completed)
            [
                'id' => 2, 'reference_no' => 'DIST-20260301-0002',
                'distribution_type' => 'phd_to_od',
                'from_department_id' => $phdId, 'to_department_id' => $odId,
                'distribution_date' => '2026-03-01', 'status' => 'completed',
                'note' => 'ការចែកចាយប្រចាំខែមីនា ២០២៦ ទៅស្រុកប្រតិបត្តិ',
                'received_date' => '2026-03-03', 'received_note' => 'បានទទួលគ្រប់ចំនួន',
                'sent_by' => $userId, 'received_by' => $userId,
                'created_by' => $userId, 'created_at' => Carbon::create(2026, 3, 1), 'updated_at' => Carbon::create(2026, 3, 3),
            ],
            // OD → HC ចំការលើ (completed)
            [
                'id' => 3, 'reference_no' => 'DIST-20260305-0001',
                'distribution_type' => 'od_to_hc',
                'from_department_id' => $odId, 'to_department_id' => 64,
                'distribution_date' => '2026-03-05', 'status' => 'completed',
                'note' => 'ចែកចាយឱសថទៅមណ្ឌលសុខភាព ចំការលើ',
                'received_date' => '2026-03-06', 'received_note' => 'ទទួលបានគ្រប់ចំនួន',
                'sent_by' => $userId, 'received_by' => $userId,
                'created_by' => $userId, 'created_at' => Carbon::create(2026, 3, 5), 'updated_at' => Carbon::create(2026, 3, 6),
            ],
            // OD → HC ស្រែគរ (completed)
            [
                'id' => 4, 'reference_no' => 'DIST-20260305-0002',
                'distribution_type' => 'od_to_hc',
                'from_department_id' => $odId, 'to_department_id' => 65,
                'distribution_date' => '2026-03-05', 'status' => 'completed',
                'note' => 'ចែកចាយឱសថទៅមណ្ឌលសុខភាព ស្រែគរ',
                'received_date' => '2026-03-07', 'received_note' => 'ទទួលបានគ្រប់ចំនួន',
                'sent_by' => $userId, 'received_by' => $userId,
                'created_by' => $userId, 'created_at' => Carbon::create(2026, 3, 5), 'updated_at' => Carbon::create(2026, 3, 7),
            ],
            // PHD → Hospital April (sent, not yet received)
            [
                'id' => 5, 'reference_no' => 'DIST-20260401-0001',
                'distribution_type' => 'phd_to_hospital',
                'from_department_id' => $phdId, 'to_department_id' => $hospitalId,
                'distribution_date' => '2026-04-01', 'status' => 'sent',
                'note' => 'ការចែកចាយប្រចាំខែមេសា ២០២៦ ទៅមន្ទីរពេទ្យ',
                'received_date' => null, 'received_note' => null,
                'sent_by' => $userId, 'received_by' => null,
                'created_by' => $userId, 'created_at' => Carbon::create(2026, 4, 1), 'updated_at' => Carbon::create(2026, 4, 1),
            ],
            // PHD → OD April (sent)
            [
                'id' => 6, 'reference_no' => 'DIST-20260401-0002',
                'distribution_type' => 'phd_to_od',
                'from_department_id' => $phdId, 'to_department_id' => $odId,
                'distribution_date' => '2026-04-01', 'status' => 'sent',
                'note' => 'ការចែកចាយប្រចាំខែមេសា ២០២៦ ទៅស្រុកប្រតិបត្តិ',
                'received_date' => null, 'received_note' => null,
                'sent_by' => $userId, 'received_by' => null,
                'created_by' => $userId, 'created_at' => Carbon::create(2026, 4, 1), 'updated_at' => Carbon::create(2026, 4, 1),
            ],
            // OD → HC កោះព្រះ (draft)
            [
                'id' => 7, 'reference_no' => 'DIST-20260404-0001',
                'distribution_type' => 'od_to_hc',
                'from_department_id' => $odId, 'to_department_id' => 66,
                'distribution_date' => '2026-04-04', 'status' => 'draft',
                'note' => 'ព្រាងចែកចាយឱសថទៅមណ្ឌលសុខភាព កោះព្រះ',
                'received_date' => null, 'received_note' => null,
                'sent_by' => null, 'received_by' => null,
                'created_by' => $userId, 'created_at' => Carbon::create(2026, 4, 4), 'updated_at' => Carbon::create(2026, 4, 4),
            ],
            // OD → HC ក្បាលរមាស (draft)
            [
                'id' => 8, 'reference_no' => 'DIST-20260404-0002',
                'distribution_type' => 'od_to_hc',
                'from_department_id' => $odId, 'to_department_id' => 67,
                'distribution_date' => '2026-04-04', 'status' => 'draft',
                'note' => 'ព្រាងចែកចាយឱសថទៅមណ្ឌលសុខភាព ក្បាលរមាស',
                'received_date' => null, 'received_note' => null,
                'sent_by' => null, 'received_by' => null,
                'created_by' => $userId, 'created_at' => Carbon::create(2026, 4, 4), 'updated_at' => Carbon::create(2026, 4, 4),
            ],
        ];
        DB::table('pharm_distributions')->insert($distributions);

        // ─────────────────────────────────────────────
        // 5. Distribution Items (បន្ទាត់ចែកចាយ)
        // ─────────────────────────────────────────────
        $distItems = [
            // Distribution #1 (PHD → Hospital, completed)
            ['distribution_id' => 1, 'medicine_id' => 1,  'quantity_sent' => 2000, 'quantity_received' => 2000, 'batch_no' => 'BN-2026-001', 'expiry_date' => '2027-06-01', 'unit_price' => 0.15, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 1, 'medicine_id' => 6,  'quantity_sent' => 3000, 'quantity_received' => 3000, 'batch_no' => 'BN-2026-006', 'expiry_date' => '2027-09-01', 'unit_price' => 0.05, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 1, 'medicine_id' => 8,  'quantity_sent' => 1000, 'quantity_received' => 1000, 'batch_no' => 'BN-2026-008', 'expiry_date' => '2027-12-01', 'unit_price' => 0.12, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 1, 'medicine_id' => 22, 'quantity_sent' => 200,  'quantity_received' => 200,  'batch_no' => 'BN-2026-022', 'expiry_date' => '2027-08-01', 'unit_price' => 2.50, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 1, 'medicine_id' => 23, 'quantity_sent' => 150,  'quantity_received' => 150,  'batch_no' => 'BN-2026-023', 'expiry_date' => '2027-10-01', 'unit_price' => 2.80, 'note' => null, 'created_at' => $now, 'updated_at' => $now],

            // Distribution #2 (PHD → OD, completed)
            ['distribution_id' => 2, 'medicine_id' => 1,  'quantity_sent' => 1500, 'quantity_received' => 1500, 'batch_no' => 'BN-2026-001', 'expiry_date' => '2027-06-01', 'unit_price' => 0.15, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 2, 'medicine_id' => 2,  'quantity_sent' => 300,  'quantity_received' => 300,  'batch_no' => 'BN-2026-002', 'expiry_date' => '2027-04-01', 'unit_price' => 1.50, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 2, 'medicine_id' => 6,  'quantity_sent' => 2500, 'quantity_received' => 2500, 'batch_no' => 'BN-2026-006', 'expiry_date' => '2027-09-01', 'unit_price' => 0.05, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 2, 'medicine_id' => 17, 'quantity_sent' => 5000, 'quantity_received' => 5000, 'batch_no' => 'BN-2026-017', 'expiry_date' => '2027-11-01', 'unit_price' => 0.03, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 2, 'medicine_id' => 21, 'quantity_sent' => 800,  'quantity_received' => 800,  'batch_no' => 'BN-2026-021', 'expiry_date' => '2027-07-01', 'unit_price' => 0.30, 'note' => null, 'created_at' => $now, 'updated_at' => $now],

            // Distribution #3 (OD → HC ចំការលើ, completed)
            ['distribution_id' => 3, 'medicine_id' => 1,  'quantity_sent' => 200, 'quantity_received' => 200, 'batch_no' => 'BN-2026-001', 'expiry_date' => '2027-06-01', 'unit_price' => 0.15, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 3, 'medicine_id' => 6,  'quantity_sent' => 300, 'quantity_received' => 300, 'batch_no' => 'BN-2026-006', 'expiry_date' => '2027-09-01', 'unit_price' => 0.05, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 3, 'medicine_id' => 17, 'quantity_sent' => 500, 'quantity_received' => 500, 'batch_no' => 'BN-2026-017', 'expiry_date' => '2027-11-01', 'unit_price' => 0.03, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 3, 'medicine_id' => 21, 'quantity_sent' => 100, 'quantity_received' => 100, 'batch_no' => 'BN-2026-021', 'expiry_date' => '2027-07-01', 'unit_price' => 0.30, 'note' => null, 'created_at' => $now, 'updated_at' => $now],

            // Distribution #4 (OD → HC ស្រែគរ, completed)
            ['distribution_id' => 4, 'medicine_id' => 1,  'quantity_sent' => 200, 'quantity_received' => 200, 'batch_no' => 'BN-2026-001', 'expiry_date' => '2027-06-01', 'unit_price' => 0.15, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 4, 'medicine_id' => 6,  'quantity_sent' => 250, 'quantity_received' => 250, 'batch_no' => 'BN-2026-006', 'expiry_date' => '2027-09-01', 'unit_price' => 0.05, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 4, 'medicine_id' => 10, 'quantity_sent' => 300, 'quantity_received' => 300, 'batch_no' => 'BN-2026-010', 'expiry_date' => '2027-05-01', 'unit_price' => 0.04, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 4, 'medicine_id' => 19, 'quantity_sent' => 400, 'quantity_received' => 400, 'batch_no' => 'BN-2026-019', 'expiry_date' => '2027-08-01', 'unit_price' => 0.04, 'note' => null, 'created_at' => $now, 'updated_at' => $now],

            // Distribution #5 (PHD → Hospital April, sent - not received yet)
            ['distribution_id' => 5, 'medicine_id' => 3,  'quantity_sent' => 1000, 'quantity_received' => 0, 'batch_no' => 'BN-2026-003', 'expiry_date' => '2027-11-01', 'unit_price' => 0.20, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 5, 'medicine_id' => 12, 'quantity_sent' => 800,  'quantity_received' => 0, 'batch_no' => 'BN-2026-012', 'expiry_date' => '2028-01-01', 'unit_price' => 0.15, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 5, 'medicine_id' => 13, 'quantity_sent' => 600,  'quantity_received' => 0, 'batch_no' => 'BN-2026-013', 'expiry_date' => '2027-12-01', 'unit_price' => 0.18, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 5, 'medicine_id' => 24, 'quantity_sent' => 100,  'quantity_received' => 0, 'batch_no' => 'BN-2026-024', 'expiry_date' => '2027-09-01', 'unit_price' => 2.20, 'note' => null, 'created_at' => $now, 'updated_at' => $now],

            // Distribution #6 (PHD → OD April, sent)
            ['distribution_id' => 6, 'medicine_id' => 4,  'quantity_sent' => 2000, 'quantity_received' => 0, 'batch_no' => 'BN-2026-004', 'expiry_date' => '2027-10-01', 'unit_price' => 0.08, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 6, 'medicine_id' => 5,  'quantity_sent' => 1500, 'quantity_received' => 0, 'batch_no' => 'BN-2026-005', 'expiry_date' => '2027-08-01', 'unit_price' => 0.10, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 6, 'medicine_id' => 15, 'quantity_sent' => 1000, 'quantity_received' => 0, 'batch_no' => 'BN-2026-015', 'expiry_date' => '2027-07-01', 'unit_price' => 0.08, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 6, 'medicine_id' => 18, 'quantity_sent' => 3000, 'quantity_received' => 0, 'batch_no' => 'BN-2026-018', 'expiry_date' => '2027-11-01', 'unit_price' => 0.02, 'note' => null, 'created_at' => $now, 'updated_at' => $now],

            // Distribution #7 (OD → HC កោះព្រះ, draft)
            ['distribution_id' => 7, 'medicine_id' => 1,  'quantity_sent' => 150, 'quantity_received' => 0, 'batch_no' => 'BN-2026-001', 'expiry_date' => '2027-06-01', 'unit_price' => 0.15, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 7, 'medicine_id' => 6,  'quantity_sent' => 200, 'quantity_received' => 0, 'batch_no' => 'BN-2026-006', 'expiry_date' => '2027-09-01', 'unit_price' => 0.05, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 7, 'medicine_id' => 7,  'quantity_sent' => 50,  'quantity_received' => 0, 'batch_no' => 'BN-2026-007', 'expiry_date' => '2027-04-01', 'unit_price' => 1.20, 'note' => null, 'created_at' => $now, 'updated_at' => $now],

            // Distribution #8 (OD → HC ក្បាលរមាស, draft)
            ['distribution_id' => 8, 'medicine_id' => 6,  'quantity_sent' => 300, 'quantity_received' => 0, 'batch_no' => 'BN-2026-006', 'expiry_date' => '2027-09-01', 'unit_price' => 0.05, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 8, 'medicine_id' => 10, 'quantity_sent' => 200, 'quantity_received' => 0, 'batch_no' => 'BN-2026-010', 'expiry_date' => '2027-05-01', 'unit_price' => 0.04, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['distribution_id' => 8, 'medicine_id' => 21, 'quantity_sent' => 100, 'quantity_received' => 0, 'batch_no' => 'BN-2026-021', 'expiry_date' => '2027-07-01', 'unit_price' => 0.30, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
        ];
        DB::table('pharm_distribution_items')->insert($distItems);

        // ─────────────────────────────────────────────
        // 6. Reports (របាយការណ៍ឱសថ)
        // ─────────────────────────────────────────────
        $reports = [
            // HC ចំការលើ → OD (approved)
            [
                'id' => 1, 'reference_no' => 'RPT-20260315-0001',
                'department_id' => 64, 'parent_department_id' => $odId,
                'report_type' => 'monthly', 'period_label' => '២០២៦-០២ កុម្ភៈ',
                'period_start' => '2026-02-01', 'period_end' => '2026-02-28',
                'status' => 'approved', 'note' => 'របាយការណ៍ប្រចាំខែកុម្ភៈ',
                'reviewer_note' => 'អនុម័តហើយ',
                'submitted_by' => $userId, 'submitted_at' => '2026-03-05 08:00:00',
                'reviewed_by' => $userId, 'reviewed_at' => '2026-03-10 10:00:00',
                'created_by' => $userId, 'created_at' => Carbon::create(2026, 3, 1), 'updated_at' => Carbon::create(2026, 3, 10),
            ],
            // HC ស្រែគរ → OD (approved)
            [
                'id' => 2, 'reference_no' => 'RPT-20260315-0002',
                'department_id' => 65, 'parent_department_id' => $odId,
                'report_type' => 'monthly', 'period_label' => '២០២៦-០២ កុម្ភៈ',
                'period_start' => '2026-02-01', 'period_end' => '2026-02-28',
                'status' => 'approved', 'note' => 'របាយការណ៍ប្រចាំខែកុម្ភៈ',
                'reviewer_note' => 'បានពិនិត្យរួចរាល់',
                'submitted_by' => $userId, 'submitted_at' => '2026-03-06 09:00:00',
                'reviewed_by' => $userId, 'reviewed_at' => '2026-03-12 14:00:00',
                'created_by' => $userId, 'created_at' => Carbon::create(2026, 3, 2), 'updated_at' => Carbon::create(2026, 3, 12),
            ],
            // OD → PHD quarterly (reviewed)
            [
                'id' => 3, 'reference_no' => 'RPT-20260401-0001',
                'department_id' => $odId, 'parent_department_id' => $phdId,
                'report_type' => 'quarterly', 'period_label' => '២០២៦ ត្រីមាសទី១ (Q1)',
                'period_start' => '2026-01-01', 'period_end' => '2026-03-31',
                'status' => 'reviewed', 'note' => 'របាយការណ៍ប្រចាំត្រីមាសទី១ ឆ្នាំ ២០២៦',
                'reviewer_note' => 'កំពុងរង់ចាំការអនុម័ត',
                'submitted_by' => $userId, 'submitted_at' => '2026-04-02 08:30:00',
                'reviewed_by' => $userId, 'reviewed_at' => '2026-04-04 16:00:00',
                'created_by' => $userId, 'created_at' => Carbon::create(2026, 4, 1), 'updated_at' => Carbon::create(2026, 4, 4),
            ],
            // Hospital → PHD (submitted)
            [
                'id' => 4, 'reference_no' => 'RPT-20260401-0002',
                'department_id' => $hospitalId, 'parent_department_id' => $phdId,
                'report_type' => 'monthly', 'period_label' => '២០២៦-០៣ មីនា',
                'period_start' => '2026-03-01', 'period_end' => '2026-03-31',
                'status' => 'submitted', 'note' => 'របាយការណ៍ប្រចាំខែមីនា រាយការណ៍ទៅ មសខ',
                'reviewer_note' => null,
                'submitted_by' => $userId, 'submitted_at' => '2026-04-03 09:00:00',
                'reviewed_by' => null, 'reviewed_at' => null,
                'created_by' => $userId, 'created_at' => Carbon::create(2026, 4, 1), 'updated_at' => Carbon::create(2026, 4, 3),
            ],
            // HC កោះព្រះ → OD (submitted)
            [
                'id' => 5, 'reference_no' => 'RPT-20260401-0003',
                'department_id' => 66, 'parent_department_id' => $odId,
                'report_type' => 'monthly', 'period_label' => '២០២៦-០៣ មីនា',
                'period_start' => '2026-03-01', 'period_end' => '2026-03-31',
                'status' => 'submitted', 'note' => 'របាយការណ៍ប្រចាំខែមីនា',
                'reviewer_note' => null,
                'submitted_by' => $userId, 'submitted_at' => '2026-04-04 10:00:00',
                'reviewed_by' => null, 'reviewed_at' => null,
                'created_by' => $userId, 'created_at' => Carbon::create(2026, 4, 2), 'updated_at' => Carbon::create(2026, 4, 4),
            ],
            // HC ចំការលើ → OD March (draft)
            [
                'id' => 6, 'reference_no' => 'RPT-20260405-0001',
                'department_id' => 64, 'parent_department_id' => $odId,
                'report_type' => 'monthly', 'period_label' => '២០២៦-០៣ មីនា',
                'period_start' => '2026-03-01', 'period_end' => '2026-03-31',
                'status' => 'draft', 'note' => 'ព្រាងរបាយការណ៍ប្រចាំខែមីនា',
                'reviewer_note' => null,
                'submitted_by' => null, 'submitted_at' => null,
                'reviewed_by' => null, 'reviewed_at' => null,
                'created_by' => $userId, 'created_at' => Carbon::create(2026, 4, 5), 'updated_at' => Carbon::create(2026, 4, 5),
            ],
        ];
        DB::table('pharm_reports')->insert($reports);

        // ─────────────────────────────────────────────
        // 7. Report Items (បន្ទាត់របាយការណ៍)
        // ─────────────────────────────────────────────
        $reportItems = [
            // Report #1 (HC ចំការលើ Feb, approved)
            ['report_id' => 1, 'medicine_id' => 1,  'opening_stock' => 150, 'received_qty' => 200, 'dispensed_qty' => 180, 'adjustment_qty' => 0,  'expired_qty' => 0, 'closing_stock' => 170, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 1, 'medicine_id' => 6,  'opening_stock' => 300, 'received_qty' => 300, 'dispensed_qty' => 350, 'adjustment_qty' => 0,  'expired_qty' => 0, 'closing_stock' => 250, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 1, 'medicine_id' => 7,  'opening_stock' => 30,  'received_qty' => 50,  'dispensed_qty' => 40,  'adjustment_qty' => 0,  'expired_qty' => 0, 'closing_stock' => 40,  'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 1, 'medicine_id' => 17, 'opening_stock' => 400, 'received_qty' => 500, 'dispensed_qty' => 420, 'adjustment_qty' => 0,  'expired_qty' => 0, 'closing_stock' => 480, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 1, 'medicine_id' => 21, 'opening_stock' => 80,  'received_qty' => 100, 'dispensed_qty' => 90,  'adjustment_qty' => 0,  'expired_qty' => 0, 'closing_stock' => 90,  'note' => null, 'created_at' => $now, 'updated_at' => $now],

            // Report #2 (HC ស្រែគរ Feb, approved)
            ['report_id' => 2, 'medicine_id' => 1,  'opening_stock' => 120, 'received_qty' => 200, 'dispensed_qty' => 160, 'adjustment_qty' => 0,  'expired_qty' => 5,  'closing_stock' => 155, 'note' => 'មានឱសថផុតកំណត់ ៥ គ្រាប់', 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 2, 'medicine_id' => 6,  'opening_stock' => 250, 'received_qty' => 250, 'dispensed_qty' => 280, 'adjustment_qty' => 0,  'expired_qty' => 0,  'closing_stock' => 220, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 2, 'medicine_id' => 10, 'opening_stock' => 200, 'received_qty' => 300, 'dispensed_qty' => 250, 'adjustment_qty' => 0,  'expired_qty' => 0,  'closing_stock' => 250, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 2, 'medicine_id' => 19, 'opening_stock' => 150, 'received_qty' => 400, 'dispensed_qty' => 300, 'adjustment_qty' => 0,  'expired_qty' => 0,  'closing_stock' => 250, 'note' => null, 'created_at' => $now, 'updated_at' => $now],

            // Report #3 (OD Q1 quarterly, reviewed)
            ['report_id' => 3, 'medicine_id' => 1,  'opening_stock' => 2000, 'received_qty' => 1500, 'dispensed_qty' => 0,   'adjustment_qty' => -400, 'expired_qty' => 0,  'closing_stock' => 3100, 'note' => 'ចែកចាយទៅមណ្ឌលសុខភាព ៤០០', 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 3, 'medicine_id' => 6,  'opening_stock' => 3000, 'received_qty' => 2500, 'dispensed_qty' => 0,   'adjustment_qty' => -550, 'expired_qty' => 0,  'closing_stock' => 4950, 'note' => 'ចែកចាយទៅ មស ៥៥០', 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 3, 'medicine_id' => 2,  'opening_stock' => 500,  'received_qty' => 300,  'dispensed_qty' => 0,   'adjustment_qty' => -100, 'expired_qty' => 10, 'closing_stock' => 690,  'note' => 'ផុតកំណត់ ១០ ដប', 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 3, 'medicine_id' => 17, 'opening_stock' => 4000, 'received_qty' => 5000, 'dispensed_qty' => 0,   'adjustment_qty' => -1000,'expired_qty' => 0,  'closing_stock' => 8000, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 3, 'medicine_id' => 21, 'opening_stock' => 600,  'received_qty' => 800,  'dispensed_qty' => 0,   'adjustment_qty' => -200, 'expired_qty' => 0,  'closing_stock' => 1200, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 3, 'medicine_id' => 15, 'opening_stock' => 800,  'received_qty' => 0,    'dispensed_qty' => 0,   'adjustment_qty' => -150, 'expired_qty' => 0,  'closing_stock' => 650,  'note' => null, 'created_at' => $now, 'updated_at' => $now],

            // Report #4 (Hospital → PHD March, submitted)
            ['report_id' => 4, 'medicine_id' => 1,  'opening_stock' => 1000, 'received_qty' => 2000, 'dispensed_qty' => 1800, 'adjustment_qty' => 0,   'expired_qty' => 0,  'closing_stock' => 1200, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 4, 'medicine_id' => 6,  'opening_stock' => 2000, 'received_qty' => 3000, 'dispensed_qty' => 3500, 'adjustment_qty' => 0,   'expired_qty' => 0,  'closing_stock' => 1500, 'note' => 'តម្រូវការខ្ពស់', 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 4, 'medicine_id' => 8,  'opening_stock' => 500,  'received_qty' => 1000, 'dispensed_qty' => 900,  'adjustment_qty' => 0,   'expired_qty' => 0,  'closing_stock' => 600,  'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 4, 'medicine_id' => 22, 'opening_stock' => 100,  'received_qty' => 200,  'dispensed_qty' => 180,  'adjustment_qty' => 0,   'expired_qty' => 0,  'closing_stock' => 120,  'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 4, 'medicine_id' => 23, 'opening_stock' => 80,   'received_qty' => 150,  'dispensed_qty' => 130,  'adjustment_qty' => 0,   'expired_qty' => 0,  'closing_stock' => 100,  'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 4, 'medicine_id' => 12, 'opening_stock' => 300,  'received_qty' => 0,    'dispensed_qty' => 200,  'adjustment_qty' => 0,   'expired_qty' => 0,  'closing_stock' => 100,  'note' => 'សន្និធិទាប ត្រូវវការបន្ថែម', 'created_at' => $now, 'updated_at' => $now],

            // Report #5 (HC កោះព្រះ March, submitted)
            ['report_id' => 5, 'medicine_id' => 1,  'opening_stock' => 100, 'received_qty' => 0,   'dispensed_qty' => 60,  'adjustment_qty' => 0, 'expired_qty' => 0, 'closing_stock' => 40,  'note' => 'សន្និធិទាប', 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 5, 'medicine_id' => 6,  'opening_stock' => 200, 'received_qty' => 0,   'dispensed_qty' => 120, 'adjustment_qty' => 0, 'expired_qty' => 0, 'closing_stock' => 80,  'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 5, 'medicine_id' => 17, 'opening_stock' => 300, 'received_qty' => 0,   'dispensed_qty' => 180, 'adjustment_qty' => 0, 'expired_qty' => 0, 'closing_stock' => 120, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 5, 'medicine_id' => 21, 'opening_stock' => 50,  'received_qty' => 0,   'dispensed_qty' => 30,  'adjustment_qty' => 0, 'expired_qty' => 0, 'closing_stock' => 20,  'note' => 'ត្រូវការបន្ថែម ORS', 'created_at' => $now, 'updated_at' => $now],

            // Report #6 (HC ចំការលើ March, draft)
            ['report_id' => 6, 'medicine_id' => 1,  'opening_stock' => 170, 'received_qty' => 0,   'dispensed_qty' => 100, 'adjustment_qty' => 0, 'expired_qty' => 0, 'closing_stock' => 70,  'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 6, 'medicine_id' => 6,  'opening_stock' => 250, 'received_qty' => 0,   'dispensed_qty' => 150, 'adjustment_qty' => 0, 'expired_qty' => 0, 'closing_stock' => 100, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['report_id' => 6, 'medicine_id' => 17, 'opening_stock' => 480, 'received_qty' => 0,   'dispensed_qty' => 200, 'adjustment_qty' => 0, 'expired_qty' => 0, 'closing_stock' => 280, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
        ];
        DB::table('pharm_report_items')->insert($reportItems);
    }
}
