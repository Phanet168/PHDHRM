<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plan_approvals')) {
            Schema::table('plan_approvals', function (Blueprint $table) {
                if (!Schema::hasColumn('plan_approvals', 'workflow_level')) {
                    $table->unsignedTinyInteger('workflow_level')->default(1)->after('org_unit_id');
                }

                if (!Schema::hasColumn('plan_approvals', 'review_scope')) {
                    $table->string('review_scope', 50)->nullable()->after('workflow_level');
                }
            });
        }

        if (Schema::hasTable('plan_comments')) {
            Schema::table('plan_comments', function (Blueprint $table) {
                if (!Schema::hasColumn('plan_comments', 'comment_type')) {
                    $table->string('comment_type', 50)->default('general')->after('user_id');
                }
            });
        }

        if (Schema::hasTable('plan_attachments')) {
            Schema::table('plan_attachments', function (Blueprint $table) {
                if (!Schema::hasColumn('plan_attachments', 'attachment_type')) {
                    $table->string('attachment_type', 50)->default('supporting_document')->after('original_name');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('plan_attachments') && Schema::hasColumn('plan_attachments', 'attachment_type')) {
            Schema::table('plan_attachments', function (Blueprint $table) {
                $table->dropColumn('attachment_type');
            });
        }

        if (Schema::hasTable('plan_comments') && Schema::hasColumn('plan_comments', 'comment_type')) {
            Schema::table('plan_comments', function (Blueprint $table) {
                $table->dropColumn('comment_type');
            });
        }

        if (Schema::hasTable('plan_approvals')) {
            Schema::table('plan_approvals', function (Blueprint $table) {
                foreach (['workflow_level', 'review_scope'] as $column) {
                    if (Schema::hasColumn('plan_approvals', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
