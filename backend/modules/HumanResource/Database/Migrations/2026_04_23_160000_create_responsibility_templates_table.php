<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\HumanResource\Entities\UserAssignment;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('responsibility_templates')) {
            return;
        }

        Schema::create('responsibility_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('module_key', 64);
            $table->string('template_key', 80);
            $table->string('name', 190);
            $table->string('name_km', 190)->nullable();
            $table->unsignedBigInteger('position_id')->nullable();
            $table->unsignedBigInteger('responsibility_id');
            $table->json('action_presets_json')->nullable();
            $table->enum('default_scope_type', UserAssignment::scopeOptions())
                ->default(UserAssignment::SCOPE_SELF_AND_CHILDREN);
            $table->integer('sort_order')->default(100);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['module_key', 'template_key'], 'ux_resp_templates_module_template');
            $table->index(['module_key', 'is_active'], 'idx_resp_templates_module_active');
            $table->index(['responsibility_id', 'is_active'], 'idx_resp_templates_role_active');
            $table->index(['position_id', 'is_active'], 'idx_resp_templates_position_active');

            $table->foreign('position_id', 'fk_resp_templates_position')
                ->references('id')
                ->on('positions')
                ->nullOnDelete();

            $table->foreign('responsibility_id', 'fk_resp_templates_role')
                ->references('id')
                ->on('system_roles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responsibility_templates');
    }
};

