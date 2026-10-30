<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('about_org_units', fn (Blueprint $table) => $table->json('leaders')->nullable());
    }

    public function down(): void
    {
        Schema::table('about_org_units', fn (Blueprint $table) => $table->dropColumn('leaders'));
    }
};
