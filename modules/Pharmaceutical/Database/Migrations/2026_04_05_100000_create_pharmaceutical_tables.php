<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /*
        |----------------------------------------------------------------------
        | pharm_categories – ប្រភេទឱសថ (medicine categories)
        |----------------------------------------------------------------------
        */
        Schema::create('pharm_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);              // e.g. Antibiotics, Analgesics
            $table->string('name_kh', 255)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        /*
        |----------------------------------------------------------------------
        | pharm_medicines – មុខឱសថ (medicine master list)
        |----------------------------------------------------------------------
        */
        Schema::create('pharm_medicines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('code', 50)->unique();          // unique medicine code
            $table->string('name', 255);                    // international name
            $table->string('name_kh', 255)->nullable();     // Khmer name
            $table->string('dosage_form', 100)->nullable(); // tablet, capsule, syrup …
            $table->string('strength', 100)->nullable();    // 500mg, 250mg/5ml …
            $table->string('unit', 50);                     // tablet, bottle, box …
            $table->string('manufacturer', 255)->nullable();
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('category_id');
            $table->index('code');
        });

        /*
        |----------------------------------------------------------------------
        | pharm_facility_stocks – សន្និធិឱសថតាមគ្រឹះស្ថាន
        |   (stock per facility / department  – PHD, Hospital, OD, HC)
        |----------------------------------------------------------------------
        */
        Schema::create('pharm_facility_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id');       // facility (department)
            $table->unsignedBigInteger('medicine_id');
            $table->decimal('quantity', 14, 2)->default(0);    // current stock qty
            $table->string('batch_no', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['department_id', 'medicine_id', 'batch_no'], 'pharm_fs_dept_med_batch_unique');
            $table->index('department_id');
            $table->index('medicine_id');
            $table->index('expiry_date');
        });

        /*
        |----------------------------------------------------------------------
        | pharm_distributions – ការចែកចាយឱសថ (distribution header)
        |
        | flow: PHD → Hospital | PHD → OD | OD → HC
        |----------------------------------------------------------------------
        */
        Schema::create('pharm_distributions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 100)->nullable();
            $table->string('distribution_type', 30);  // phd_to_hospital, phd_to_od, od_to_hc
            $table->unsignedBigInteger('from_department_id');
            $table->unsignedBigInteger('to_department_id');
            $table->date('distribution_date');
            $table->string('status', 30)->default('draft'); // draft, sent, received, partial, completed
            $table->text('note')->nullable();
            $table->date('received_date')->nullable();
            $table->text('received_note')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['distribution_type', 'status']);
            $table->index('from_department_id');
            $table->index('to_department_id');
            $table->index('distribution_date');
        });

        /*
        |----------------------------------------------------------------------
        | pharm_distribution_items – បន្ទាត់ចែកចាយ (distribution line items)
        |----------------------------------------------------------------------
        */
        Schema::create('pharm_distribution_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('distribution_id');
            $table->unsignedBigInteger('medicine_id');
            $table->decimal('quantity_sent', 14, 2)->default(0);
            $table->decimal('quantity_received', 14, 2)->default(0);
            $table->string('batch_no', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('distribution_id');
            $table->index('medicine_id');
        });

        /*
        |----------------------------------------------------------------------
        | pharm_reports – របាយការណ៍ឱសថ (periodic reports – bottom-up)
        |
        | HC → OD → PHD  |  Hospital → PHD
        |----------------------------------------------------------------------
        */
        Schema::create('pharm_reports', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 100)->nullable();
            $table->unsignedBigInteger('department_id');          // reporting facility
            $table->unsignedBigInteger('parent_department_id')->nullable(); // report to
            $table->string('report_type', 30);            // monthly, quarterly, annual, adhoc
            $table->string('period_label', 50)->nullable(); // e.g. "2026-03", "2026-Q1"
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('status', 30)->default('draft'); // draft, submitted, reviewed, approved
            $table->text('note')->nullable();
            $table->text('reviewer_note')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'report_type', 'status']);
            $table->index('parent_department_id');
            $table->index(['period_start', 'period_end']);
        });

        /*
        |----------------------------------------------------------------------
        | pharm_report_items – បន្ទាត់របាយការណ៍ (report line items)
        |----------------------------------------------------------------------
        */
        Schema::create('pharm_report_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id');
            $table->unsignedBigInteger('medicine_id');
            $table->decimal('opening_stock', 14, 2)->default(0);
            $table->decimal('received_qty', 14, 2)->default(0);
            $table->decimal('dispensed_qty', 14, 2)->default(0);  // used / dispensed
            $table->decimal('adjustment_qty', 14, 2)->default(0); // +/- adjustment
            $table->decimal('expired_qty', 14, 2)->default(0);
            $table->decimal('closing_stock', 14, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('report_id');
            $table->index('medicine_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharm_report_items');
        Schema::dropIfExists('pharm_reports');
        Schema::dropIfExists('pharm_distribution_items');
        Schema::dropIfExists('pharm_distributions');
        Schema::dropIfExists('pharm_facility_stocks');
        Schema::dropIfExists('pharm_medicines');
        Schema::dropIfExists('pharm_categories');
    }
};
