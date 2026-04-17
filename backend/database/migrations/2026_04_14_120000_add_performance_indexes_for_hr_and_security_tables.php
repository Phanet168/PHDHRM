<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (!$this->hasIndex('employees', 'employees_is_active_department_sub_idx')) {
                    $table->index(['is_active', 'department_id', 'sub_department_id'], 'employees_is_active_department_sub_idx');
                }
                if (!$this->hasIndex('employees', 'employees_employee_id_idx')) {
                    $table->index('employee_id', 'employees_employee_id_idx');
                }
                if (!$this->hasIndex('employees', 'employees_official_id_10_idx')) {
                    $table->index('official_id_10', 'employees_official_id_10_idx');
                }
                if (!$this->hasIndex('employees', 'employees_service_state_idx')) {
                    $table->index('service_state', 'employees_service_state_idx');
                }
            });
        }

        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if (!$this->hasIndex('attendances', 'attendances_employee_time_idx')) {
                    $table->index(['employee_id', 'time'], 'attendances_employee_time_idx');
                }
                if (!$this->hasIndex('attendances', 'attendances_time_idx')) {
                    $table->index('time', 'attendances_time_idx');
                }
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (!$this->hasIndex('notifications', 'notifications_notifiable_read_created_idx')) {
                    $table->index(['notifiable_type', 'notifiable_id', 'read_at', 'created_at'], 'notifications_notifiable_read_created_idx');
                }
                if (!$this->hasIndex('notifications', 'notifications_read_created_idx')) {
                    $table->index(['read_at', 'created_at'], 'notifications_read_created_idx');
                }
            });
        }

        if (Schema::hasTable('activity_log')) {
            Schema::table('activity_log', function (Blueprint $table) {
                if (!$this->hasIndex('activity_log', 'activity_log_created_at_idx')) {
                    $table->index('created_at', 'activity_log_created_at_idx');
                }
            });
        }

        if (Schema::hasTable('failed_jobs')) {
            Schema::table('failed_jobs', function (Blueprint $table) {
                if (!$this->hasIndex('failed_jobs', 'failed_jobs_failed_at_idx')) {
                    $table->index('failed_at', 'failed_jobs_failed_at_idx');
                }
            });
        }

        if (Schema::hasTable('password_resets')) {
            Schema::table('password_resets', function (Blueprint $table) {
                if (!$this->hasIndex('password_resets', 'password_resets_created_at_idx')) {
                    $table->index('created_at', 'password_resets_created_at_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'employees_is_active_department_sub_idx');
                $this->dropIndexIfExists($table, 'employees_employee_id_idx');
                $this->dropIndexIfExists($table, 'employees_official_id_10_idx');
                $this->dropIndexIfExists($table, 'employees_service_state_idx');
            });
        }

        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'attendances_employee_time_idx');
                $this->dropIndexIfExists($table, 'attendances_time_idx');
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'notifications_notifiable_read_created_idx');
                $this->dropIndexIfExists($table, 'notifications_read_created_idx');
            });
        }

        if (Schema::hasTable('activity_log')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'activity_log_created_at_idx');
            });
        }

        if (Schema::hasTable('failed_jobs')) {
            Schema::table('failed_jobs', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'failed_jobs_failed_at_idx');
            });
        }

        if (Schema::hasTable('password_resets')) {
            Schema::table('password_resets', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'password_resets_created_at_idx');
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection
            ->table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();

        return (bool) $result;
    }

    private function dropIndexIfExists(Blueprint $table, string $indexName): void
    {
        try {
            $table->dropIndex($indexName);
        } catch (\Throwable $e) {
            // Ignore if index does not exist.
        }
    }
};
