<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_full_right_officer')->default(false)->after('official_id_10');
            $table->date('service_start_date')->nullable()->after('is_full_right_officer');
            $table->date('full_right_date')->nullable()->after('service_start_date');
            $table->string('legal_document_type', 40)->nullable()->after('full_right_date');
            $table->string('legal_document_number', 120)->nullable()->after('legal_document_type');
            $table->date('legal_document_date')->nullable()->after('legal_document_number');
            $table->text('legal_document_subject')->nullable()->after('legal_document_date');
        });

        // Initial backfill from existing data.
        DB::table('employees')
            ->whereNull('service_start_date')
            ->update(['service_start_date' => DB::raw('joining_date')]);

        DB::table('employees')
            ->whereNotNull('official_id_10')
            ->where('official_id_10', '<>', '')
            ->update(['is_full_right_officer' => true]);

        Schema::create('employee_legal_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('document_number', 120);
            $table->date('document_date')->nullable();
            $table->text('document_subject')->nullable();
            $table->date('effective_date')->nullable()->comment('Usually full-right date');
            $table->boolean('is_current')->default(true);
            $table->text('note')->nullable();
            $table->updateCreatedBy();
            $table->timestamps();

            $table->index(['employee_id', 'is_current'], 'employee_legal_records_employee_current_idx');
            $table->index(['document_type', 'document_date'], 'employee_legal_records_type_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_legal_records');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'is_full_right_officer',
                'service_start_date',
                'full_right_date',
                'legal_document_type',
                'legal_document_number',
                'legal_document_date',
                'legal_document_subject',
            ]);
        });
    }
};
