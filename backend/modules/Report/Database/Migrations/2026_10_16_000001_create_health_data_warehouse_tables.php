<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Disaggregated HIS data (data-warehouse style, similar to DHIS2):
 *   fact = period × facility × data element × [diagnosis] × [age group] × [sex] × [option] → value
 * Facilities are the HRM org units in `departments` (OD, hospitals, health centres).
 * Import templates describe where each table sits in the HIS HC1 / HO2 workbooks.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ធាតុទិន្នន័យ — what is counted (e.g. OPD new cases, discharges, deaths, ANC visit 1)
        Schema::create('report_data_elements', function (Blueprint $t) {
            $t->id();
            $t->string('uid', 40)->unique();          // stable key derived from template block + labels
            $t->string('code', 64)->unique();         // human code, e.g. HC1-OPD-0001
            $t->text('name');
            $t->string('section')->nullable();        // block title, e.g. "២. បញ្ហាសុខភាពករណីថ្មី"
            $t->string('value_type', 16)->default('count'); // count | number | percent
            $t->string('aggregation', 8)->default('sum');   // how months roll up: sum | avg | last
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        // ក្រុមអាយុ — created from header labels ("0-28 ថ្ងៃ", "≥ 65 ឆ្នាំ", "< 20ឆ្នាំ" …)
        Schema::create('report_age_groups', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();         // normalised label, e.g. 0-28d, 65+y, <20y
            $t->string('label', 64);
            $t->unsignedInteger('min_days')->nullable();
            $t->unsignedInteger('max_days')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        // Other disaggregations: zone (តំបន់ក/ខ/គ), residence (ក្នុង/ក្រៅស្រុក), person (ម្តាយ/កូន)
        Schema::create('report_category_options', function (Blueprint $t) {
            $t->id();
            $t->string('category', 32);
            $t->string('code', 64);
            $t->string('label', 128);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->unique(['category', 'code']);
        });

        // ICD-10 diagnoses found in the HIS morbidity tables
        Schema::create('report_diagnoses', function (Blueprint $t) {
            $t->id();
            $t->string('icd10', 16)->unique();
            $t->string('name');
            $t->string('group_name')->nullable();     // e.g. "1. ផ្នែកជំងឺទូទៅ"
            $t->timestamps();
        });

        Schema::create('report_import_templates', function (Blueprint $t) {
            $t->id();
            $t->string('code', 16)->unique();         // HC1, HO2
            $t->string('name');
            $t->string('facility_type', 64)->nullable(); // org_unit_types.code expected for the facility
            $t->text('description')->nullable();
            $t->timestamps();
        });

        // One table inside a sheet of a template
        Schema::create('report_import_blocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('template_id')->constrained('report_import_templates')->cascadeOnDelete();
            $t->string('code', 32);                   // e.g. OPD, IPD, ANC
            $t->string('title');
            $t->string('sheet');
            $t->json('header_rows');                  // e.g. [11, 12]
            $t->unsignedInteger('row_start');
            $t->unsignedInteger('row_end');
            $t->json('label_cols');                   // e.g. ["B"]
            $t->string('code_col', 8)->nullable();    // ICD-10 column for diagnosis tables
            $t->string('kind', 16)->default('items'); // diagnosis | items
            $t->string('measure')->nullable();        // what the age/sex columns count when no header says so
            $t->string('first_col', 8)->nullable();   // first data column (skip numbering columns)
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['template_id', 'code']);
        });

        // One uploaded workbook; deleting a batch removes its facts
        Schema::create('report_import_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('template_id')->constrained('report_import_templates');
            $t->unsignedBigInteger('facility_id')->index();       // departments.id
            $t->foreignId('period_id')->constrained('report_periods');
            $t->string('file_name');
            $t->string('file_hash', 64)->index();
            $t->unsignedInteger('facts_count')->default(0);
            $t->json('summary')->nullable();          // per-block counts and validation results
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });

        Schema::create('report_facts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('period_id')->constrained('report_periods')->cascadeOnDelete();
            $t->unsignedBigInteger('facility_id');                // departments.id
            $t->foreignId('data_element_id')->constrained('report_data_elements')->cascadeOnDelete();
            $t->foreignId('diagnosis_id')->nullable()->constrained('report_diagnoses')->nullOnDelete();
            $t->foreignId('age_group_id')->nullable()->constrained('report_age_groups')->nullOnDelete();
            $t->char('sex', 1)->nullable();                       // M | F (null = both / not split)
            $t->foreignId('option_id')->nullable()->constrained('report_category_options')->nullOnDelete();
            // MySQL unique indexes ignore NULLs, so the dimension combination is also kept as one string
            $t->string('dim_key', 64);
            $t->decimal('value', 20, 4);
            $t->foreignId('batch_id')->nullable()->constrained('report_import_batches')->cascadeOnDelete();
            $t->string('source_cell', 64)->nullable();            // e.g. "Page 1!F16"
            $t->timestamps();

            $t->unique(['period_id', 'facility_id', 'data_element_id', 'dim_key'], 'report_facts_unique');
            $t->index(['facility_id', 'period_id']);
            $t->index(['data_element_id', 'period_id']);
            $t->index('diagnosis_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_facts');
        Schema::dropIfExists('report_import_batches');
        Schema::dropIfExists('report_import_blocks');
        Schema::dropIfExists('report_import_templates');
        Schema::dropIfExists('report_diagnoses');
        Schema::dropIfExists('report_category_options');
        Schema::dropIfExists('report_age_groups');
        Schema::dropIfExists('report_data_elements');
    }
};
