<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('org_unit_type_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_type_id')
                ->constrained('org_unit_types')
                ->cascadeOnDelete();
            $table->foreignId('position_id')
                ->constrained('positions')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('hierarchy_rank')->nullable();
            $table->boolean('is_leadership')->default(false);
            $table->boolean('can_approve')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['unit_type_id', 'position_id'], 'org_unit_type_positions_unique');
            $table->index(['unit_type_id', 'is_active', 'hierarchy_rank'], 'org_unit_type_positions_type_rank_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_unit_type_positions');
    }
};
