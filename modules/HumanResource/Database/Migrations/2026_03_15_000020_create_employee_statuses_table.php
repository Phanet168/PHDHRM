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
        Schema::create('employee_statuses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 50)->nullable()->unique();
            $table->string('name_km', 191)->nullable();
            $table->string('name_en', 191);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->updateCreatedBy();
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'employee_statuses_active_sort_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_statuses');
    }
};
