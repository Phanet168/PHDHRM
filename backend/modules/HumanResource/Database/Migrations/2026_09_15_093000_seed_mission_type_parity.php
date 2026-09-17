<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Brings the mission_types master table to parity with the legacy Mission::TYPES
     * const, using firstOrCreate-style inserts so admin edits/disabled rows survive reruns.
     */
    public function up(): void
    {
        if (!Schema::hasTable('mission_types')) {
            return;
        }

        $now = now();
        $rows = [
            ['code' => 'inspection', 'name' => 'Inspection visit', 'name_km' => 'ចុះត្រួតពិនិត្យមណ្ឌល/អង្គភាព', 'sort_order' => 2],
            ['code' => 'community_visit', 'name' => 'Community visit', 'name_km' => 'ចុះបំពេញការងារតាមភូមិ/សហគមន៍', 'sort_order' => 3],
            ['code' => 'provincial_assignment', 'name' => 'Provincial assignment', 'name_km' => 'បំពេញការងារតាមខេត្ត', 'sort_order' => 4],
            ['code' => 'meeting', 'name' => 'Meeting / workshop', 'name_km' => 'ប្រជុំ/សិក្ខាសាលា', 'sort_order' => 5],
        ];

        foreach ($rows as $row) {
            if (DB::table('mission_types')->where('code', $row['code'])->exists()) {
                continue;
            }

            DB::table('mission_types')->insert([
                'code' => $row['code'],
                'name' => $row['name'],
                'name_km' => $row['name_km'],
                'description' => null,
                'is_active' => 1,
                'sort_order' => $row['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Preserve admin data; do not remove types on rollback.
    }
};
