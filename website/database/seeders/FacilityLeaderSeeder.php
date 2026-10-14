<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\FacilityLeader;
use Illuminate\Database\Seeder;

class FacilityLeaderSeeder extends Seeder
{
    public function run(): void
    {
        $hospital = Facility::where('slug', 'referral-hospital-stung-treng')->first();

        FacilityLeader::create([
            'facility_id' => $hospital?->id,
            'name' => 'វេជ្ជបណ្ឌិត ប្រធានមន្ទីរពេទ្យ',
            'title' => 'ប្រធានមន្ទីរពេទ្យបង្អែកខេត្ត',
            'sort_order' => 0,
        ]);

        $titles = ['ប្រធានមន្ទីរ', 'អនុប្រធានមន្ទីរ', 'អនុប្រធានមន្ទីរ', 'អនុប្រធានមន្ទីរ'];
        foreach ($titles as $order => $title) {
            FacilityLeader::create([
                'facility_id' => null,
                'name' => 'វេជ្ជបណ្ឌិត ថ្នាក់ដឹកនាំ',
                'title' => $title,
                'sort_order' => $order,
            ]);
        }
    }
}
