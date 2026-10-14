<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $hospital = Facility::where('facility_type', 'referral_hospital')->first();
        $healthCenter = Facility::where('facility_type', 'health_center')->first();

        $users = [
            ['name' => 'Super Admin', 'email' => 'superadmin@phdstg.gov.kh', 'role' => 'Super Admin', 'facility_id' => null],
            ['name' => 'Website Admin', 'email' => 'webadmin@phdstg.gov.kh', 'role' => 'Website Admin', 'facility_id' => null],
            ['name' => 'Hospital Admin', 'email' => 'hospitaladmin@phdstg.gov.kh', 'role' => 'Hospital Admin', 'facility_id' => $hospital?->id],
            ['name' => 'Health Center Admin', 'email' => 'hcadmin@phdstg.gov.kh', 'role' => 'Health Center Admin', 'facility_id' => $healthCenter?->id],
            ['name' => 'Editor', 'email' => 'editor@phdstg.gov.kh', 'role' => 'Editor', 'facility_id' => null],
            ['name' => 'Reviewer', 'email' => 'reviewer@phdstg.gov.kh', 'role' => 'Reviewer', 'facility_id' => null],
        ];

        foreach ($users as $data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('password'),
                'facility_id' => $data['facility_id'],
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $user->assignRole($data['role']);
        }
    }
}
