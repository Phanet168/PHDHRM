<?php

namespace Modules\Planning\Database\Seeders;

use Illuminate\Database\Seeder;

class PlanningDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanningPermissionSeeder::class,
            PlanningMasterDataSeeder::class,
            PlanningOrgUnitSeeder::class,
        ]);
    }
}
