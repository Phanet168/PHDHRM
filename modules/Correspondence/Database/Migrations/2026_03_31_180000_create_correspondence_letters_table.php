<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('correspondence_letters', function (Blueprint $table) {
            $table->id();
            $table->string('letter_type', 20); // incoming | outgoing
            $table->string('registry_no', 100)->nullable();
            $table->string('letter_no', 150)->nullable();
            $table->string('subject', 500);
            $table->string('from_org', 255)->nullable();
            $table->string('to_org', 255)->nullable();
            $table->string('priority', 20)->default('normal'); // normal | urgent | confidential
            $table->string('status', 30)->default('pending'); // pending | in_progress | completed | archived
            $table->date('letter_date')->nullable();
            $table->date('received_date')->nullable();
            $table->date('due_date')->nullable();
            $table->text('summary')->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['letter_type', 'status']);
            $table->index(['letter_date']);
            $table->index(['received_date']);
            $table->index(['due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_letters');
    }
};
