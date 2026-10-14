<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->enum('facility_type', ['referral_hospital', 'health_center', 'health_post', 'operational_district_office'])
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->enum('facility_type', ['referral_hospital', 'health_center', 'health_post'])->change();
        });
    }
};
