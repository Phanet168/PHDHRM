<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A legal document can now be a link to a file hosted elsewhere (e.g. a
     * shared Google Drive PDF) instead of an uploaded file. Such records often
     * have no reliably known number/date, so those three columns become
     * nullable rather than being filled with invented values.
     */
    public function up(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->string('external_url', 2048)->nullable()->after('file_path');
            $table->string('external_format', 10)->nullable()->after('external_url');
            $table->string('document_number')->nullable()->change();
            $table->date('issue_date')->nullable()->change();
            $table->unsignedSmallInteger('year')->nullable()->change();
        });
    }

    /** Only the added columns are dropped: rows with null number/date/year cannot be made NOT NULL again safely. */
    public function down(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->dropColumn(['external_url', 'external_format']);
        });
    }
};
