<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'national_education_level')) {
                $table->unsignedTinyInteger('national_education_level')
                    ->nullable()
                    ->after('highest_educational_qualification');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'national_education_level')) {
                $table->dropColumn('national_education_level');
            }
        });
    }
};
