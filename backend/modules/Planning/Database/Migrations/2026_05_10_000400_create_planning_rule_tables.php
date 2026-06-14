<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_cluster_permissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $table->foreignId('activity_cluster_id')->constrained('activity_clusters')->cascadeOnDelete();
            $table->boolean('can_create')->default(true);
            $table->boolean('can_review')->default(false);
            $table->boolean('can_approve')->default(false);
            $table->boolean('can_consolidate')->default(false);
            $table->timestamps();

            $table->unique(['org_unit_id', 'activity_cluster_id'], 'uq_unit_cluster_permissions_scope');
        });

        Schema::create('cluster_chart_account_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('activity_cluster_id')->constrained('activity_clusters')->cascadeOnDelete();
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_default')->default(false);
            $table->decimal('min_amount', 16, 2)->nullable();
            $table->decimal('max_amount', 16, 2)->nullable();
            $table->timestamps();

            $table->unique(['activity_cluster_id', 'chart_of_account_id'], 'uq_cluster_coa_rules_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_chart_account_rules');
        Schema::dropIfExists('unit_cluster_permissions');
    }
};
