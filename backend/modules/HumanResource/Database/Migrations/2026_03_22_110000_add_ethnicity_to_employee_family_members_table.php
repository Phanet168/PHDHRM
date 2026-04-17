<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employee_family_members', 'ethnicity')) {
            Schema::table('employee_family_members', function (Blueprint $table) {
                $table->string('ethnicity', 120)->nullable()->after('nationality');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_family_members', 'ethnicity')) {
            Schema::table('employee_family_members', function (Blueprint $table) {
                $table->dropColumn('ethnicity');
            });
        }
    }
};

