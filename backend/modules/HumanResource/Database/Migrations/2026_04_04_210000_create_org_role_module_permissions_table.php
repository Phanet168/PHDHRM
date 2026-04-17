<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HumanResource\Entities\UserOrgRole;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('org_role_module_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module_key', 64);
            $table->string('action_key', 64);
            $table->string('org_role', 32);
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['module_key', 'action_key', 'org_role'], 'org_role_module_perm_unique');
            $table->index(['module_key', 'action_key', 'is_active'], 'org_role_module_perm_module_action_idx');
        });

        $seededAt = now();
        $defaultRows = [
            ['module_key' => 'correspondence', 'action_key' => 'create_incoming', 'org_role' => UserOrgRole::ROLE_MANAGER],
            ['module_key' => 'correspondence', 'action_key' => 'create_incoming', 'org_role' => UserOrgRole::ROLE_DEPUTY_HEAD],
            ['module_key' => 'correspondence', 'action_key' => 'create_incoming', 'org_role' => UserOrgRole::ROLE_HEAD],
            ['module_key' => 'correspondence', 'action_key' => 'create_outgoing', 'org_role' => UserOrgRole::ROLE_MANAGER],
            ['module_key' => 'correspondence', 'action_key' => 'create_outgoing', 'org_role' => UserOrgRole::ROLE_DEPUTY_HEAD],
            ['module_key' => 'correspondence', 'action_key' => 'create_outgoing', 'org_role' => UserOrgRole::ROLE_HEAD],
            ['module_key' => 'correspondence', 'action_key' => 'delegate', 'org_role' => UserOrgRole::ROLE_DEPUTY_HEAD],
            ['module_key' => 'correspondence', 'action_key' => 'delegate', 'org_role' => UserOrgRole::ROLE_HEAD],
            ['module_key' => 'correspondence', 'action_key' => 'office_comment', 'org_role' => UserOrgRole::ROLE_MANAGER],
            ['module_key' => 'correspondence', 'action_key' => 'deputy_review', 'org_role' => UserOrgRole::ROLE_DEPUTY_HEAD],
            ['module_key' => 'correspondence', 'action_key' => 'director_decision', 'org_role' => UserOrgRole::ROLE_HEAD],
            ['module_key' => 'correspondence', 'action_key' => 'distribute', 'org_role' => UserOrgRole::ROLE_MANAGER],
            ['module_key' => 'correspondence', 'action_key' => 'distribute', 'org_role' => UserOrgRole::ROLE_DEPUTY_HEAD],
            ['module_key' => 'correspondence', 'action_key' => 'distribute', 'org_role' => UserOrgRole::ROLE_HEAD],
            ['module_key' => 'correspondence', 'action_key' => 'close', 'org_role' => UserOrgRole::ROLE_MANAGER],
            ['module_key' => 'correspondence', 'action_key' => 'close', 'org_role' => UserOrgRole::ROLE_DEPUTY_HEAD],
            ['module_key' => 'correspondence', 'action_key' => 'close', 'org_role' => UserOrgRole::ROLE_HEAD],
            ['module_key' => 'correspondence', 'action_key' => 'acknowledge', 'org_role' => UserOrgRole::ROLE_MANAGER],
            ['module_key' => 'correspondence', 'action_key' => 'acknowledge', 'org_role' => UserOrgRole::ROLE_DEPUTY_HEAD],
            ['module_key' => 'correspondence', 'action_key' => 'acknowledge', 'org_role' => UserOrgRole::ROLE_HEAD],
            ['module_key' => 'correspondence', 'action_key' => 'feedback', 'org_role' => UserOrgRole::ROLE_MANAGER],
            ['module_key' => 'correspondence', 'action_key' => 'feedback', 'org_role' => UserOrgRole::ROLE_DEPUTY_HEAD],
            ['module_key' => 'correspondence', 'action_key' => 'feedback', 'org_role' => UserOrgRole::ROLE_HEAD],
        ];

        if (!empty($defaultRows)) {
            DB::table('org_role_module_permissions')->insert(
                collect($defaultRows)->map(function (array $row) use ($seededAt) {
                    return array_merge($row, [
                        'is_active' => true,
                        'created_at' => $seededAt,
                        'updated_at' => $seededAt,
                    ]);
                })->all()
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_role_module_permissions');
    }
};

