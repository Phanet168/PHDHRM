<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employee_family_members', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_family_members', 'present_address_state')) {
                $table->string('present_address_state', 191)->nullable()->after('birth_place_village');
            }
            if (!Schema::hasColumn('employee_family_members', 'present_address_city')) {
                $table->string('present_address_city', 191)->nullable()->after('present_address_state');
            }
            if (!Schema::hasColumn('employee_family_members', 'present_address_commune')) {
                $table->string('present_address_commune', 191)->nullable()->after('present_address_city');
            }
            if (!Schema::hasColumn('employee_family_members', 'present_address_village')) {
                $table->string('present_address_village', 191)->nullable()->after('present_address_commune');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_family_members', function (Blueprint $table) {
            $dropColumns = [];
            foreach ([
                'present_address_state',
                'present_address_city',
                'present_address_commune',
                'present_address_village',
            ] as $column) {
                if (Schema::hasColumn('employee_family_members', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};

