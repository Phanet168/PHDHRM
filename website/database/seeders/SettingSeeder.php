<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'org_name_km' => 'មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្តស្ទឹងត្រែង',
            'org_name_en' => 'Stung Treng Provincial Health Department',
            'phone' => '074 210 000',
            'email' => 'info@phdstg.gov.kh',
            'address' => 'ក្រុងស្ទឹងត្រែង ខេត្តស្ទឹងត្រែង',
            'office_hours' => 'ចន្ទ–សុក្រ 07:30–17:00',
            'facebook_url' => null,
            'telegram_url' => null,
            'youtube_url' => null,
            'seo_title' => 'មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្តស្ទឹងត្រែង',
            'seo_description' => 'ព័ត៌មានសុខាភិបាល មូលដ្ឋានសុខាភិបាល និងការផ្សព្វផ្សាយសុខភាពសម្រាប់ប្រជាពលរដ្ឋក្នុងខេត្តស្ទឹងត្រែង។',
            'hrm_url' => env('HRM_URL', 'http://localhost/PHDHRM/backend/public'),
        ];

        foreach ($defaults as $key => $value) {
            Setting::set($key, $value);
        }
    }
}
