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
            if (!Schema::hasColumn('employees', 'birth_place_state_id')) {
                $table->string('birth_place_state_id', 2)->nullable()->after('date_of_birth');
            }
            if (!Schema::hasColumn('employees', 'birth_place_city_id')) {
                $table->string('birth_place_city_id', 4)->nullable()->after('birth_place_state_id');
            }
            if (!Schema::hasColumn('employees', 'birth_place_commune_id')) {
                $table->string('birth_place_commune_id', 6)->nullable()->after('birth_place_city_id');
            }
            if (!Schema::hasColumn('employees', 'birth_place_village_id')) {
                $table->string('birth_place_village_id', 8)->nullable()->after('birth_place_commune_id');
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
                'birth_place_state_id',
                'birth_place_city_id',
                'birth_place_commune_id',
                'birth_place_village_id',
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

