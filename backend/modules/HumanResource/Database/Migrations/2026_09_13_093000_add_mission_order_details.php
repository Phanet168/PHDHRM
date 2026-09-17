<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', fn (Blueprint $table) => $table->json('order_details')->nullable());
        Schema::table('mission_assignments', function (Blueprint $table) {
            $table->string('name_on_order')->nullable();
            $table->string('position_on_order')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('missions', fn (Blueprint $table) => $table->dropColumn('order_details'));
        Schema::table('mission_assignments', fn (Blueprint $table) => $table->dropColumn(['name_on_order', 'position_on_order', 'sort_order']));
    }
};
