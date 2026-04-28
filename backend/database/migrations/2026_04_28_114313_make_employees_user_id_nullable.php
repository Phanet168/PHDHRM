<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix dirty user_id=0 rows before altering column (0 = no real user)
        DB::table('employees')->where('user_id', 0)->update(['user_id' => null]);

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Restore non-nullable (set NULL back to 0 as sentinel)
        DB::table('employees')->whereNull('user_id')->update(['user_id' => 0]);

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
