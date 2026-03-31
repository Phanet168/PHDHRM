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
        Schema::table('positions', function (Blueprint $table) {
            $table->string('position_name_km')->nullable()->after('position_name');
            $table->unsignedTinyInteger('position_rank')->nullable()->after('position_details');
            $table->boolean('is_prov_level')->default(true)->after('position_rank');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn(['position_name_km', 'position_rank', 'is_prov_level']);
        });
    }
};
