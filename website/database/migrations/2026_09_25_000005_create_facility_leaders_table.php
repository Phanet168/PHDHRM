<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_leaders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('title');
            $table->string('photo')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_leaders');
    }
};
