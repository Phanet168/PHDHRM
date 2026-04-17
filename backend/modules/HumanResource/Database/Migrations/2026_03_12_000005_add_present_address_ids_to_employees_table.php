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
        if (!Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'present_address_state_id')) {
                $table->string('present_address_state_id', 2)->nullable()->after('present_address_country');
            }
            if (!Schema::hasColumn('employees', 'present_address_city_id')) {
                $table->string('present_address_city_id', 4)->nullable()->after('present_address_state_id');
            }
            if (!Schema::hasColumn('employees', 'present_address_commune_id')) {
                $table->string('present_address_commune_id', 6)->nullable()->after('present_address_city_id');
            }
            if (!Schema::hasColumn('employees', 'present_address_village_id')) {
                $table->string('present_address_village_id', 8)->nullable()->after('present_address_commune_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $columns = [];
            foreach ([
                'present_address_state_id',
                'present_address_city_id',
                'present_address_commune_id',
                'present_address_village_id',
            ] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $columns[] = $column;
                }
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};

