<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_folders');
    }
};
