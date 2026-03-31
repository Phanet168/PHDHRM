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
            if (!Schema::hasColumn('employees', 'chronic_disease_history')) {
                $table->text('chronic_disease_history')->nullable()->after('health_condition');
            }
            if (!Schema::hasColumn('employees', 'severe_disease_history')) {
                $table->text('severe_disease_history')->nullable()->after('chronic_disease_history');
            }
            if (!Schema::hasColumn('employees', 'surgery_history')) {
                $table->text('surgery_history')->nullable()->after('severe_disease_history');
            }
            if (!Schema::hasColumn('employees', 'regular_medication')) {
                $table->text('regular_medication')->nullable()->after('surgery_history');
            }
            if (!Schema::hasColumn('employees', 'allergy_reaction')) {
                $table->text('allergy_reaction')->nullable()->after('regular_medication');
            }
            if (!Schema::hasColumn('employees', 'covid_vaccine_dose')) {
                $table->string('covid_vaccine_dose', 30)->nullable()->after('allergy_reaction');
            }
            if (!Schema::hasColumn('employees', 'covid_vaccine_name')) {
                $table->string('covid_vaccine_name', 191)->nullable()->after('covid_vaccine_dose');
            }
            if (!Schema::hasColumn('employees', 'covid_vaccine_date')) {
                $table->date('covid_vaccine_date')->nullable()->after('covid_vaccine_name');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $columns = [
                'chronic_disease_history',
                'severe_disease_history',
                'surgery_history',
                'regular_medication',
                'allergy_reaction',
                'covid_vaccine_dose',
                'covid_vaccine_name',
                'covid_vaccine_date',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

