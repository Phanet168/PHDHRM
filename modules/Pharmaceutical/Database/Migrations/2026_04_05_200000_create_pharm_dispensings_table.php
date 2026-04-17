<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /*
        |--------------------------------------------------------------
        | pharm_dispensings – ការផ្តល់ឱសថដល់អ្នកជំងឺ (header)
        |--------------------------------------------------------------
        */
        Schema::create('pharm_dispensings', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 100)->nullable();
            $table->unsignedBigInteger('department_id');          // Hospital or HC
            $table->date('dispensing_date');
            $table->string('patient_name', 255);
            $table->string('patient_id_no', 100)->nullable();     // ID card / patient code
            $table->string('patient_gender', 10)->nullable();     // M / F
            $table->unsignedSmallInteger('patient_age')->nullable();
            $table->string('diagnosis', 500)->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('dispensed_by')->nullable(); // user who dispensed
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('department_id');
            $table->index('dispensing_date');
            $table->index('patient_name');
        });

        /*
        |--------------------------------------------------------------
        | pharm_dispensing_items – បន្ទាត់ឱសថផ្តល់ (line items)
        |--------------------------------------------------------------
        */
        Schema::create('pharm_dispensing_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dispensing_id');
            $table->unsignedBigInteger('medicine_id');
            $table->decimal('quantity', 14, 2);
            $table->string('batch_no', 100)->nullable();
            $table->string('dosage_instruction', 255)->nullable(); // e.g. "1x3 after meals"
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('dispensing_id');
            $table->index('medicine_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharm_dispensing_items');
        Schema::dropIfExists('pharm_dispensings');
    }
};
