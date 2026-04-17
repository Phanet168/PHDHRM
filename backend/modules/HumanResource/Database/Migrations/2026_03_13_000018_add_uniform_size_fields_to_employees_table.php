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
            if (!Schema::hasColumn('employees', 'uniform_shirt_size')) {
                $table->string('uniform_shirt_size', 30)->nullable()->after('disabilities_desc');
            }
            if (!Schema::hasColumn('employees', 'uniform_pants_size')) {
                $table->string('uniform_pants_size', 30)->nullable()->after('uniform_shirt_size');
            }
            if (!Schema::hasColumn('employees', 'uniform_shoe_size')) {
                $table->string('uniform_shoe_size', 30)->nullable()->after('uniform_pants_size');
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
            if (Schema::hasColumn('employees', 'uniform_shoe_size')) {
                $table->dropColumn('uniform_shoe_size');
            }
            if (Schema::hasColumn('employees', 'uniform_pants_size')) {
                $table->dropColumn('uniform_pants_size');
            }
            if (Schema::hasColumn('employees', 'uniform_shirt_size')) {
                $table->dropColumn('uniform_shirt_size');
            }
        });
    }
};

