<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('employee_docs')) {
            return;
        }

        DB::statement('ALTER TABLE `employee_docs` MODIFY `doc_title` VARCHAR(255) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('employee_docs')) {
            return;
        }

        DB::table('employee_docs')->whereNull('doc_title')->update(['doc_title' => '']);
        DB::statement('ALTER TABLE `employee_docs` MODIFY `doc_title` VARCHAR(255) NOT NULL');
    }
};
