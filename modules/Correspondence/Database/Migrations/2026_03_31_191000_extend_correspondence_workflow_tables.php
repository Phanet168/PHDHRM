<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('correspondence_letters')) {
            Schema::table('correspondence_letters', function (Blueprint $table) {
                if (!Schema::hasColumn('correspondence_letters', 'origin_department_id')) {
                    $table->unsignedBigInteger('origin_department_id')->nullable()->after('attachment_path');
                }
                if (!Schema::hasColumn('correspondence_letters', 'assigned_department_id')) {
                    $table->unsignedBigInteger('assigned_department_id')->nullable()->after('origin_department_id');
                }
                if (!Schema::hasColumn('correspondence_letters', 'current_handler_user_id')) {
                    $table->unsignedBigInteger('current_handler_user_id')->nullable()->after('assigned_department_id');
                }
                if (!Schema::hasColumn('correspondence_letters', 'current_step')) {
                    $table->string('current_step', 60)->nullable()->after('current_handler_user_id');
                }
                if (!Schema::hasColumn('correspondence_letters', 'final_decision')) {
                    $table->string('final_decision', 30)->nullable()->after('current_step');
                }
                if (!Schema::hasColumn('correspondence_letters', 'decision_note')) {
                    $table->text('decision_note')->nullable()->after('final_decision');
                }
                if (!Schema::hasColumn('correspondence_letters', 'decision_at')) {
                    $table->dateTime('decision_at')->nullable()->after('decision_note');
                }
                if (!Schema::hasColumn('correspondence_letters', 'completed_at')) {
                    $table->dateTime('completed_at')->nullable()->after('decision_at');
                }
                $table->index('current_step', 'correspondence_letters_current_step_idx');
                $table->index('origin_department_id', 'correspondence_letters_origin_dept_idx');
                $table->index('assigned_department_id', 'correspondence_letters_assigned_dept_idx');
            });
        }

        if (!Schema::hasTable('correspondence_letter_actions')) {
            Schema::create('correspondence_letter_actions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('letter_id');
                $table->string('step_key', 60)->nullable();
                $table->string('action_type', 60);
                $table->unsignedBigInteger('acted_by')->nullable();
                $table->unsignedBigInteger('target_user_id')->nullable();
                $table->unsignedBigInteger('target_department_id')->nullable();
                $table->text('note')->nullable();
                $table->json('meta_json')->nullable();
                $table->dateTime('acted_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['letter_id', 'action_type'], 'corr_actions_letter_type_idx');
                $table->index(['acted_by'], 'corr_actions_acted_by_idx');
            });
        }

        if (!Schema::hasTable('correspondence_letter_distributions')) {
            Schema::create('correspondence_letter_distributions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('letter_id');
                $table->unsignedBigInteger('target_department_id')->nullable();
                $table->unsignedBigInteger('target_user_id')->nullable();
                $table->unsignedBigInteger('distributed_by')->nullable();
                $table->dateTime('distributed_at')->nullable();
                $table->dateTime('acknowledged_at')->nullable();
                $table->text('acknowledgement_note')->nullable();
                $table->text('feedback_note')->nullable();
                $table->dateTime('feedback_at')->nullable();
                $table->string('status', 30)->default('pending_ack');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['letter_id', 'status'], 'corr_dist_letter_status_idx');
                $table->index(['target_user_id'], 'corr_dist_target_user_idx');
                $table->index(['target_department_id'], 'corr_dist_target_dept_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('correspondence_letter_distributions')) {
            Schema::drop('correspondence_letter_distributions');
        }

        if (Schema::hasTable('correspondence_letter_actions')) {
            Schema::drop('correspondence_letter_actions');
        }

        if (Schema::hasTable('correspondence_letters')) {
            Schema::table('correspondence_letters', function (Blueprint $table) {
                $table->dropIndex('correspondence_letters_current_step_idx');
                $table->dropIndex('correspondence_letters_origin_dept_idx');
                $table->dropIndex('correspondence_letters_assigned_dept_idx');

                foreach ([
                    'completed_at',
                    'decision_at',
                    'decision_note',
                    'final_decision',
                    'current_step',
                    'current_handler_user_id',
                    'assigned_department_id',
                    'origin_department_id',
                ] as $column) {
                    if (Schema::hasColumn('correspondence_letters', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
