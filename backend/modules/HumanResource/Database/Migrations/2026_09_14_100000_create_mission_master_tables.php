<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['mission_types', 'transport_types'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name', 150);
                $table->string('name_km', 255);
                if ($name === 'mission_types') {
                    $table->text('description')->nullable();
                }
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_types');
        Schema::dropIfExists('mission_types');
    }
};
