<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            // Existing schedules remain unallocated until an administrator assigns a unit.
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->time('morning_end_time')->nullable();
            $table->time('afternoon_start_time')->nullable();
            $table->boolean('is_duty')->default(false);
            $table->boolean('is_default')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropIndex(['department_id']);
            $table->dropColumn(['department_id', 'morning_end_time', 'afternoon_start_time', 'is_duty', 'is_default']);
        });
    }
};
