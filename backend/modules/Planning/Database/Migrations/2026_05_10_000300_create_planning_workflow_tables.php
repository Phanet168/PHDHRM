<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_approvals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->unsignedTinyInteger('workflow_level')->default(1);
            $table->string('review_scope', 50)->nullable();
            $table->unsignedBigInteger('acted_by')->nullable();
            $table->string('action', 50);
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50)->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'action'], 'idx_plan_approvals_plan_action');
        });

        Schema::create('plan_comments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('plan_item_id')->nullable()->constrained('plan_items')->nullOnDelete();
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('comment_type', 50)->default('general');
            $table->text('comment');
            $table->timestamps();

            $table->index(['plan_id', 'created_at'], 'idx_plan_comments_plan_date');
        });

        Schema::create('plan_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('plan_item_id')->nullable()->constrained('plan_items')->nullOnDelete();
            $table->unsignedBigInteger('uploaded_by');
            $table->string('disk', 50)->default('public');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('attachment_type', 50)->default('supporting_document');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();

            $table->index(['plan_id'], 'idx_plan_attachments_plan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_attachments');
        Schema::dropIfExists('plan_comments');
        Schema::dropIfExists('plan_approvals');
    }
};
