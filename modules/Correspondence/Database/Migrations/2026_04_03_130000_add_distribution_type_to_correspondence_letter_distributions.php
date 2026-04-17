<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('correspondence_letter_distributions', function (Blueprint $table) {
            if (!Schema::hasColumn('correspondence_letter_distributions', 'distribution_type')) {
                $table->string('distribution_type', 10)->default('to')->after('target_user_id');
                $table->index(['distribution_type']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('correspondence_letter_distributions', function (Blueprint $table) {
            if (Schema::hasColumn('correspondence_letter_distributions', 'distribution_type')) {
                $table->dropIndex(['distribution_type']);
                $table->dropColumn('distribution_type');
            }
        });
    }
};
