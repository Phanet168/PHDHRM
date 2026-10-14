<?php

namespace Database\Seeders;

use App\Models\Facility;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FacilitySeeder extends Seeder
{
    private const DISTRICTS = [
        'ស្រុកសេសាន' => ['ឃុំសេសាន', 'ឃុំកំពូល', 'ឃុំសំគរលូ'],
        'ស្រុកថាឡាបរិវត្ត' => ['ឃុំថាឡាបរិវត្ត', 'ឃុំគគី', 'ឃុំក្បាលរមាស'],
        'ស្រុកសៀមបូក' => ['ឃុំសៀមបូក', 'ឃុំសំរោង', 'ឃុំលើសាធារណៈ'],
        'ស្រុកឧកញ៉ាដឹក' => ['ឃុំសទឹង', 'ឃុំហាត់', 'ឃុំពាក់ល្វា'],
        'ក្រុងស្ទឹងត្រែង' => ['សង្កាត់ស្ទឹងត្រែង', 'សង្កាត់សុគន្ធិម', 'សង្កាត់ជើងវែង'],
    ];

    public function run(): void
    {
        $districts = array_keys(self::DISTRICTS);
        $stock = ['stock-1.jpg', 'stock-2.jpg', 'stock-3.jpg', 'stock-4.jpg'];
        $codeSeq = 1;

        $referralHospital = Facility::create([
            'code' => 'RH-001',
            'facility_type' => 'referral_hospital',
            'name_km' => 'មន្ទីរពេទ្យបង្អែកខេត្តស្ទឹងត្រែង',
            'name_en' => 'Stung Treng Provincial Referral Hospital',
            'slug' => 'referral-hospital-stung-treng',
            'district' => 'ស្រុកសេសាន',
            'commune' => 'ខេត្តស្ទឹងត្រែង',
            'phone' => '074 210 000',
            'email' => 'hospital@phdstg.gov.kh',
            'opening_hours' => '24 ម៉ោង / 7 ថ្ងៃ',
            'address' => 'ក្រុងស្ទឹងត្រែង',
            'cover_image' => 'images/stock/stock-1.jpg',
            'description' => 'មន្ទីរពេទ្យបង្អែកខេត្តស្ទឹងត្រែងផ្តល់សេវាពិនិត្យ ព្យាបាល សង្គ្រោះបន្ទាន់ និងសេវាឯកទេសសម្រាប់ប្រជាពលរដ្ឋក្នុងខេត្ត។',
            'history' => 'មន្ទីរពេទ្យត្រូវបានបង្កើតឡើងដើម្បីផ្តល់សេវាសុខភាពដល់ប្រជាពលរដ្ឋក្នុងខេត្តស្ទឹងត្រែង។',
            'vision' => 'ប្រជាពលរដ្ឋទទួលបានសុខភាព និងសុខុមាលភាពល្អ',
            'mission' => 'ផ្តល់ និងគ្រប់គ្រងសេវាសុខាភិបាលប្រកបដោយគុណភាព',
            'patients_count' => 12568,
            'staff_count' => 82,
            'services_count' => 15,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $codeSeq++;

        foreach (['សង្គ្រោះបន្ទាន់', 'ជំងឺទូទៅ', 'សម្ភព', 'កុមារ', 'មន្ទីរពិសោធន៍'] as $order => $serviceName) {
            $referralHospital->services()->create(['icon' => '✚', 'name' => $serviceName, 'sort_order' => $order]);
        }

        $hospitalNames = ['មន្ទីរពេទ្យបង្អែកតំបន់សេសាន', 'មន្ទីរពេទ្យបង្អែកតំបន់ថាឡាបរិវត្ត', 'មន្ទីរពេទ្យបង្អែកតំបន់សៀមបូក'];
        foreach ($hospitalNames as $i => $name) {
            $district = $districts[$i % count($districts)];
            Facility::create([
                'code' => sprintf('RH-%03d', $codeSeq++),
                'facility_type' => 'referral_hospital',
                'name_km' => $name,
                'slug' => Str::slug($name) . '-' . ($i + 1),
                'district' => $district,
                'commune' => self::DISTRICTS[$district][0],
                'phone' => sprintf('074 210 %03d', $codeSeq),
                'opening_hours' => '24 ម៉ោង / 7 ថ្ងៃ',
                'address' => $district,
                'cover_image' => 'images/stock/' . $stock[$i % count($stock)],
                'description' => 'ផ្តល់សេវាព្យាបាល និងសេវាឯកទេសសម្រាប់ប្រជាពលរដ្ឋក្នុងមូលដ្ឋាន។',
                'patients_count' => random_int(3000, 9000),
                'staff_count' => random_int(20, 50),
                'services_count' => random_int(6, 12),
                'status' => 'published',
                'published_at' => now(),
            ]);
        }

        for ($i = 1; $i <= 21; $i++) {
            $district = $districts[$i % count($districts)];
            $communes = self::DISTRICTS[$district];
            Facility::create([
                'code' => sprintf('HC-%03d', $i),
                'facility_type' => 'health_center',
                'name_km' => 'មណ្ឌលសុខភាព ' . ($i + 1),
                'slug' => 'health-center-' . ($i + 1),
                'district' => $district,
                'commune' => $communes[$i % count($communes)],
                'phone' => sprintf('074 210 %03d', $codeSeq++),
                'opening_hours' => 'ចន្ទ–សុក្រ 07:30–17:00',
                'address' => $district,
                'cover_image' => 'images/stock/' . $stock[$i % count($stock)],
                'description' => 'ផ្តល់សេវាសុខាភិបាលមូលដ្ឋានសម្រាប់ប្រជាពលរដ្ឋក្នុងឃុំ។',
                'patients_count' => random_int(500, 3000),
                'staff_count' => random_int(3, 12),
                'services_count' => random_int(3, 8),
                'status' => $i <= 18 ? 'published' : 'draft',
                'published_at' => $i <= 18 ? now() : null,
            ]);
        }

        for ($i = 1; $i <= 8; $i++) {
            $district = $districts[$i % count($districts)];
            $communes = self::DISTRICTS[$district];
            Facility::create([
                'code' => sprintf('HP-%03d', $i),
                'facility_type' => 'health_post',
                'name_km' => 'ប៉ុស្តិ៍សុខភាព ' . $i,
                'slug' => 'health-post-' . $i,
                'district' => $district,
                'commune' => $communes[$i % count($communes)],
                'phone' => sprintf('074 210 %03d', $codeSeq++),
                'opening_hours' => 'ចន្ទ–សុក្រ 07:30–17:00',
                'address' => $district,
                'cover_image' => 'images/stock/' . $stock[$i % count($stock)],
                'description' => 'ចំណុចផ្តល់សេវាសុខភាពដំបូងសម្រាប់ភូមិនានា។',
                'patients_count' => random_int(100, 900),
                'staff_count' => random_int(1, 4),
                'services_count' => random_int(2, 5),
                'status' => 'published',
                'published_at' => now(),
            ]);
        }
    }
}
