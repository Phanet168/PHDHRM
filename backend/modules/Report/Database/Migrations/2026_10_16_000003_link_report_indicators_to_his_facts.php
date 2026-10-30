<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An indicator can take its value from the HIS facts: the sum of one data element, optionally narrowed to
 * a sex, age group, diagnosis or option (e.g. "ចំនួនពិគ្រោះជំងឺក្រៅ ស្រី" = OPD new cases where sex = F).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_indicators', function (Blueprint $t) {
            $t->foreignId('his_element_id')->nullable()->after('aggregation')->constrained('report_data_elements')->nullOnDelete();
            $t->char('his_sex', 1)->nullable()->after('his_element_id');
            $t->foreignId('his_age_group_id')->nullable()->after('his_sex')->constrained('report_age_groups')->nullOnDelete();
            $t->foreignId('his_diagnosis_id')->nullable()->after('his_age_group_id')->constrained('report_diagnoses')->nullOnDelete();
            $t->foreignId('his_option_id')->nullable()->after('his_diagnosis_id')->constrained('report_category_options')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('report_indicators', function (Blueprint $t) {
            foreach (['his_option_id', 'his_diagnosis_id', 'his_age_group_id', 'his_element_id'] as $fk) {
                $t->dropConstrainedForeignId($fk);
            }
            $t->dropColumn('his_sex');
        });
    }
};
