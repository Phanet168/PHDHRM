<?php

namespace Database\Seeders;

use App\Models\Partner;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    public function run(): void
    {
        $partners = ['WHO', 'UNICEF', 'KOICA', 'JICA', 'កម្មវិធីជាតិ', 'អង្គការដៃគូ'];

        foreach ($partners as $order => $name) {
            Partner::create([
                'name' => $name,
                'sort_order' => $order,
                'active' => true,
            ]);
        }
    }
}
