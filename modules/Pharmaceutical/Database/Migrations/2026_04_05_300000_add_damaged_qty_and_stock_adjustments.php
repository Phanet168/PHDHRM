<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add damaged_qty to pharm_report_items
        Schema::table('pharm_report_items', function (Blueprint $table) {
            $table->decimal('damaged_qty', 14, 2)->default(0)->after('expired_qty');
        });

        // Stock adjustments table – track damage, expired write-offs, corrections, losses
        Schema::create('pharm_stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 100)->nullable();
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('medicine_id');
            $table->string('adjustment_type', 30);         // damaged, expired, loss, correction
            $table->decimal('quantity', 14, 2)->default(0); // always positive – deducted from stock
            $table->string('batch_no', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('adjustment_date');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('adjusted_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'adjustment_type']);
            $table->index('medicine_id');
            $table->index('adjustment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharm_stock_adjustments');

        Schema::table('pharm_report_items', function (Blueprint $table) {
            $table->dropColumn('damaged_qty');
        });
    }
};
