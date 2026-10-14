<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_review_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['page_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_review_logs');
    }
};
