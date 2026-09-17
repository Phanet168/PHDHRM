<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 093000 owns these columns. Fail before DDL if that prerequisite is missing.
        foreach (['missions' => ['order_details'], 'mission_assignments' => ['name_on_order', 'position_on_order', 'sort_order']] as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw new RuntimeException('Run 2026_09_13_093000_add_mission_order_details first.');
                }
            }
        }

        Schema::table('missions', function (Blueprint $table) {
            // Unknown legacy provenance and workflow state deliberately remain NULL.
            $table->string('creation_path', 32)->nullable();
            $table->string('creation_source', 40)->nullable();
            $table->text('creation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('requester_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->foreignId('source_department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->foreignId('issuer_department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->foreignId('mission_type_id')->nullable()->constrained('mission_types')->restrictOnDelete();
            $table->foreignId('funding_source_id')->nullable()->constrained('funding_sources')->restrictOnDelete();
            $table->string('sponsor_name', 255)->nullable();
            $table->foreignId('transport_type_id')->nullable()->constrained('transport_types')->restrictOnDelete();
            $table->string('transport_description', 255)->nullable();
            $table->string('lifecycle_status', 40)->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('lock_version')->default(0);
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('actual_returned_at')->nullable();
            $table->dateTime('report_due_at')->nullable();
            $table->index(['creation_path', 'creation_source'], 'missions_path_source_idx');
            $table->index(['issuer_department_id', 'lifecycle_status'], 'missions_issuer_lifecycle_idx');
            $table->index(['source_department_id', 'lifecycle_status'], 'missions_source_lifecycle_idx');
            $table->index(['lifecycle_status', 'report_due_at'], 'missions_report_due_idx');
            $table->foreign('workflow_instance_id', 'missions_workflow_instance_fk')->references('id')->on('workflow_instances')->restrictOnDelete();
        });

        Schema::table('mission_assignments', function (Blueprint $table) {
            $table->string('honorific_on_order', 100)->nullable();
            $table->string('department_on_order', 255)->nullable();
        });

        Schema::table('mission_reports', function (Blueprint $table) {
            $table->string('title', 255)->nullable();
            foreach (['activities', 'results', 'issues', 'recommendations'] as $column) {
                $table->longText($column)->nullable();
            }
            $table->dateTime('submitted_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->index(['mission_id', 'submitted_at'], 'mission_reports_submission_idx');
            $table->unique(['id', 'mission_id'], 'mission_reports_id_mission_unique');
        });

        Schema::table('mission_documents', function (Blueprint $table) {
            $table->string('document_kind', 40)->default('supporting');
            $table->unsignedBigInteger('mission_report_id')->nullable();
            $table->foreign(['mission_report_id', 'mission_id'], 'mission_documents_report_mission_fk')
                ->references(['id', 'mission_id'])->on('mission_reports')->restrictOnDelete();
            $table->foreignId('correspondence_letter_id')->nullable()->constrained('correspondence_letters')->restrictOnDelete();
            $table->string('reference_number', 150)->nullable();
            $table->date('reference_date')->nullable();
            $table->string('reference_issuer', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->index(['mission_id', 'document_kind'], 'mission_documents_kind_idx');
            $table->string('path', 1000)->nullable()->change();
            $table->string('mime_type', 150)->nullable()->change();
            $table->unsignedBigInteger('size')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Never silently discard reference-only documents to restore old NOT NULL columns.
        if (DB::table('mission_documents')->whereNull('path')->orWhereNull('mime_type')->orWhereNull('size')->exists()) {
            throw new RuntimeException('Rollback blocked: export/resolve reference-only mission documents first.');
        }
        // InnoDB may discard its old automatic FK index after the composite
        // mission/kind index is added. Restore it before dropping that index.
        if (DB::getDriverName() === 'mysql' && ! DB::select("SHOW INDEX FROM mission_documents WHERE Key_name = 'mission_documents_mission_id_foreign'")) {
            Schema::table('mission_documents', fn (Blueprint $table) => $table->index('mission_id', 'mission_documents_mission_id_foreign'));
        }
        Schema::table('mission_documents', function (Blueprint $table) {
            $table->dropForeign('mission_documents_report_mission_fk');
            $table->dropForeign(['correspondence_letter_id']);
            $table->dropIndex('mission_documents_kind_idx');
            $table->dropColumn(['document_kind', 'mission_report_id', 'correspondence_letter_id', 'reference_number', 'reference_date', 'reference_issuer', 'sort_order']);
            $table->string('path', 1000)->nullable(false)->change();
            $table->string('mime_type', 150)->nullable(false)->change();
            $table->unsignedBigInteger('size')->nullable(false)->change();
        });
        Schema::table('mission_reports', function (Blueprint $table) {
            $table->dropIndex('mission_reports_submission_idx');
            $table->dropUnique('mission_reports_id_mission_unique');
            $table->dropColumn(['title', 'activities', 'results', 'issues', 'recommendations', 'submitted_at', 'revision']);
        });
        Schema::table('mission_assignments', fn (Blueprint $table) => $table->dropColumn(['honorific_on_order', 'department_on_order']));
        Schema::table('missions', function (Blueprint $table) {
            $table->dropForeign('missions_workflow_instance_fk');
            $table->dropIndex('missions_workflow_instance_fk');
            foreach (['created_by', 'requester_employee_id', 'source_department_id', 'issuer_department_id', 'mission_type_id', 'funding_source_id', 'transport_type_id'] as $column) {
                $table->dropForeign([$column]);
            }
            foreach (['missions_path_source_idx', 'missions_issuer_lifecycle_idx', 'missions_source_lifecycle_idx', 'missions_report_due_idx'] as $index) {
                $table->dropIndex($index);
            }
            $table->dropColumn(['creation_path', 'creation_source', 'creation_reason', 'created_by', 'requester_employee_id', 'source_department_id', 'issuer_department_id', 'mission_type_id', 'funding_source_id', 'sponsor_name', 'transport_type_id', 'transport_description', 'lifecycle_status', 'revision', 'lock_version', 'submitted_at', 'issued_at', 'actual_returned_at', 'report_due_at']);
        });
    }
};
