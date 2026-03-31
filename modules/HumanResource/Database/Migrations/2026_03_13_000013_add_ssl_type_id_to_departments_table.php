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
        if (!Schema::hasTable('departments') || !Schema::hasTable('gov_salary_scales')) {
            return;
        }

        if (Schema::hasColumn('departments', 'ssl_type_id')) {
            return;
        }

        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('ssl_type_id')
                ->nullable()
                ->after('unit_type_id')
                ->constrained('gov_salary_scales')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('departments') || !Schema::hasColumn('departments', 'ssl_type_id')) {
            return;
        }

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['ssl_type_id']);
            $table->dropColumn('ssl_type_id');
        });
    }
};

