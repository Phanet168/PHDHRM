<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3D.1: ONE canonical Delegation table -- temporary, additive
 * approval-authority transfer between two users. Not a second generic
 * scope/assignment system: scope_type reuses the exact same enum values as
 * user_assignments.scope_type (see Modules\HumanResource\Entities\UserAssignment),
 * and authority is a reference to an existing WorkflowDefinition.module_key,
 * never a copy of roles/permissions/assignments.
 *
 * scope_department_ids (JSON array) holds the concrete department id(s) the
 * delegated scope is rooted at. UserAssignment's "SELECTED_UNITS" is achieved
 * by multiple rows sharing one responsibility because each UserAssignment
 * row is independently a first-class governance record; a Delegation is one
 * atomic grant that must revoke as a single unit, so its selected-units case
 * is one row holding an array of self_only department ids instead -- not a
 * new scope_type value, the same self_only semantics applied to N roots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('delegator_user_id');
            $table->unsignedBigInteger('delegatee_user_id');

            // V1: only workflow approval authority is delegatable (see
            // AccessControlService::delegatableAuthoritiesFor()). authority_type
            // is kept as an explicit column (rather than assuming) so a future,
            // deliberately-designed authority type does not require a schema
            // change -- it must still go through the same single delegations
            // table, never a parallel one.
            $table->string('authority_type', 40)->default('workflow_approval');
            $table->string('authority_module_key', 64);

            $table->string('scope_type', 30);
            $table->json('scope_department_ids')->nullable();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            // Explicit, stored states only: 'active' (not revoked) / 'revoked'.
            // UPCOMING / ACTIVE-NOW / EXPIRED are computed from starts_at/ends_at
            // at read time (see Delegation::scopeActive()) -- not stored.
            $table->string('status', 20)->default('active');

            $table->text('reason')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->dateTime('revoked_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('delegator_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('delegatee_user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->index(['delegatee_user_id', 'authority_module_key', 'status'], 'delegations_delegatee_authority_status_idx');
            $table->index(['delegator_user_id'], 'delegations_delegator_idx');
            $table->index(['starts_at', 'ends_at'], 'delegations_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegations');
    }
};
