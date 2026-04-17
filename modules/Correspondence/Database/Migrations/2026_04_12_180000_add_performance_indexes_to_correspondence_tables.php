<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('correspondence_letters')) {
            Schema::table('correspondence_letters', function (Blueprint $table) {
                if (!$this->hasIndex('correspondence_letters', 'corr_letters_created_by_idx') && Schema::hasColumn('correspondence_letters', 'created_by')) {
                    $table->index('created_by', 'corr_letters_created_by_idx');
                }

                if (!$this->hasIndex('correspondence_letters', 'corr_letters_handler_idx') && Schema::hasColumn('correspondence_letters', 'current_handler_user_id')) {
                    $table->index('current_handler_user_id', 'corr_letters_handler_idx');
                }

                if (!$this->hasIndex('correspondence_letters', 'corr_letters_type_received_idx') && Schema::hasColumn('correspondence_letters', 'received_date')) {
                    $table->index(['letter_type', 'received_date', 'id'], 'corr_letters_type_received_idx');
                }

                if (!$this->hasIndex('correspondence_letters', 'corr_letters_type_sent_idx') && Schema::hasColumn('correspondence_letters', 'sent_date')) {
                    $table->index(['letter_type', 'sent_date', 'id'], 'corr_letters_type_sent_idx');
                }
            });
        }

        if (Schema::hasTable('correspondence_letter_distributions')) {
            Schema::table('correspondence_letter_distributions', function (Blueprint $table) {
                if (!$this->hasIndex('correspondence_letter_distributions', 'corr_dist_letter_type_idx') && Schema::hasColumn('correspondence_letter_distributions', 'distribution_type')) {
                    $table->index(['letter_id', 'distribution_type'], 'corr_dist_letter_type_idx');
                }

                if (!$this->hasIndex('correspondence_letter_distributions', 'corr_dist_user_status_idx')) {
                    $table->index(['target_user_id', 'status'], 'corr_dist_user_status_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('correspondence_letter_distributions')) {
            Schema::table('correspondence_letter_distributions', function (Blueprint $table) {
                if ($this->hasIndex('correspondence_letter_distributions', 'corr_dist_user_status_idx')) {
                    $table->dropIndex('corr_dist_user_status_idx');
                }
                if ($this->hasIndex('correspondence_letter_distributions', 'corr_dist_letter_type_idx')) {
                    $table->dropIndex('corr_dist_letter_type_idx');
                }
            });
        }

        if (Schema::hasTable('correspondence_letters')) {
            Schema::table('correspondence_letters', function (Blueprint $table) {
                if ($this->hasIndex('correspondence_letters', 'corr_letters_type_sent_idx')) {
                    $table->dropIndex('corr_letters_type_sent_idx');
                }
                if ($this->hasIndex('correspondence_letters', 'corr_letters_type_received_idx')) {
                    $table->dropIndex('corr_letters_type_received_idx');
                }
                if ($this->hasIndex('correspondence_letters', 'corr_letters_handler_idx')) {
                    $table->dropIndex('corr_letters_handler_idx');
                }
                if ($this->hasIndex('correspondence_letters', 'corr_letters_created_by_idx')) {
                    $table->dropIndex('corr_letters_created_by_idx');
                }
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $database = Schema::getConnection()->getDatabaseName();
            $rows = DB::select(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
                [$database, $table, $indexName]
            );

            return !empty($rows);
        }

        return false;
    }
};
