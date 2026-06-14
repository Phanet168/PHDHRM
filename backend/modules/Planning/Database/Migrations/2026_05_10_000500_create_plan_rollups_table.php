<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_rollups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parent_plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('child_plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('rollup_org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->decimal('rolled_cost', 16, 2)->default(0);
            $table->decimal('rolled_revenue', 16, 2)->default(0);
            $table->unsignedInteger('rolled_items_count')->default(0);
            $table->timestamp('rolled_at')->nullable();
            $table->unsignedBigInteger('rolled_by')->nullable();
            $table->timestamps();

            $table->unique(['parent_plan_id', 'child_plan_id'], 'uq_plan_rollups_pair');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_rollups');
    }
};
