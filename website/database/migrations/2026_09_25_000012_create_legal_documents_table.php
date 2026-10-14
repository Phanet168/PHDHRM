<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('legal_document_categories')->nullOnDelete();
            $table->string('title');
            $table->string('document_number');
            $table->string('issuing_authority');
            $table->date('issue_date');
            $table->unsignedSmallInteger('year');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
