<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('correspondence_letters')) {
            Schema::table('correspondence_letters', function (Blueprint $table) {
                if (!Schema::hasColumn('correspondence_letters', 'parent_letter_id')) {
                    $table->unsignedBigInteger('parent_letter_id')->nullable()->after('completed_at');
                }
                if (!Schema::hasColumn('correspondence_letters', 'source_distribution_id')) {
                    $table->unsignedBigInteger('source_distribution_id')->nullable()->after('parent_letter_id');
                }
                $table->index('parent_letter_id', 'corr_letters_parent_idx');
                $table->index('source_distribution_id', 'corr_letters_source_dist_idx');
            });
        }

        if (Schema::hasTable('correspondence_letter_distributions')) {
            Schema::table('correspondence_letter_distributions', function (Blueprint $table) {
                if (!Schema::hasColumn('correspondence_letter_distributions', 'child_letter_id')) {
                    $table->unsignedBigInteger('child_letter_id')->nullable()->after('target_user_id');
                }
                $table->index('child_letter_id', 'corr_dist_child_letter_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('correspondence_letter_distributions')) {
            Schema::table('correspondence_letter_distributions', function (Blueprint $table) {
                $table->dropIndex('corr_dist_child_letter_idx');
                if (Schema::hasColumn('correspondence_letter_distributions', 'child_letter_id')) {
                    $table->dropColumn('child_letter_id');
                }
            });
        }

        if (Schema::hasTable('correspondence_letters')) {
            Schema::table('correspondence_letters', function (Blueprint $table) {
                $table->dropIndex('corr_letters_source_dist_idx');
                $table->dropIndex('corr_letters_parent_idx');
                if (Schema::hasColumn('correspondence_letters', 'source_distribution_id')) {
                    $table->dropColumn('source_distribution_id');
                }
                if (Schema::hasColumn('correspondence_letters', 'parent_letter_id')) {
                    $table->dropColumn('parent_letter_id');
                }
            });
        }
    }
};
