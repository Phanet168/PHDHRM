<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('location_code')->nullable()->after('department_name');
            $table->decimal('latitude', 10, 7)->nullable()->after('location_code');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unique('location_code', 'departments_location_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique('departments_location_code_unique');
            $table->dropColumn(['location_code', 'latitude', 'longitude']);
        });
    }
};

