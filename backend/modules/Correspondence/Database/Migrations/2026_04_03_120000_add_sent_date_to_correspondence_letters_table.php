<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('correspondence_letters', function (Blueprint $table) {
            if (!Schema::hasColumn('correspondence_letters', 'sent_date')) {
                $table->date('sent_date')->nullable()->after('received_date');
                $table->index(['sent_date']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('correspondence_letters', function (Blueprint $table) {
            if (Schema::hasColumn('correspondence_letters', 'sent_date')) {
                $table->dropIndex(['sent_date']);
                $table->dropColumn('sent_date');
            }
        });
    }
};
