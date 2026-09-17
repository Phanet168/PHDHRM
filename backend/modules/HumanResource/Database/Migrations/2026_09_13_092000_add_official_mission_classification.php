<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->string('mission_type', 40)->default('other')->index();
            $table->string('order_number', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropIndex(['mission_type']);
            $table->dropColumn(['mission_type', 'order_number']);
        });
    }
};
