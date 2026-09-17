<?php

namespace Modules\HumanResource\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\HumanResource\Entities\MissionType;
use Modules\HumanResource\Entities\TransportType;

class MissionFoundationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['training', 'Training', 'វគ្គបណ្តុះបណ្តាល'],
            ['other_mission', 'Other mission', 'ការចុះបេសកកម្មផ្សេងៗ'],
        ] as $order => [$code, $name, $khmer]) {
            // Preserve administrator edits and disabled master entries on reruns.
            MissionType::firstOrCreate(['code' => $code], [
                'name' => $name, 'name_km' => $khmer, 'sort_order' => $order, 'is_active' => true,
            ]);
        }
        foreach ([
            ['unit_vehicle', 'Unit vehicle', 'រថយន្តអង្គភាព'],
            ['rented_vehicle', 'Rented vehicle', 'រថយន្តជួល'],
            ['private_vehicle', 'Private vehicle', 'រថយន្តផ្ទាល់ខ្លួន'],
            ['motorcycle', 'Motorcycle', 'ម៉ូតូ'],
            ['airplane', 'Airplane', 'យន្តហោះ'],
            ['boat', 'Boat', 'ទូក'],
            ['other', 'Other', 'ផ្សេងៗ'],
        ] as $order => [$code, $name, $khmer]) {
            TransportType::firstOrCreate(['code' => $code], [
                'name' => $name, 'name_km' => $khmer, 'sort_order' => $order, 'is_active' => true,
            ]);
        }
        // funding_sources is owned by Planning; do not seed or rewrite it here.
    }
}
