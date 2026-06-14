<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('indicators')) {
            return;
        }

        Schema::table('indicators', function (Blueprint $table) {
            if (!Schema::hasColumn('indicators', 'activity_cluster_id')) {
                $table->foreignId('activity_cluster_id')
                    ->nullable()
                    ->after('uuid')
                    ->constrained('activity_clusters')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('indicators', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(1)->after('description');
            }
        });

        if (!Schema::hasColumn('indicators', 'activity_cluster_id')) {
            return;
        }

        try {
            Schema::table('indicators', function (Blueprint $table) {
                $table->index(['activity_cluster_id', 'is_active'], 'idx_indicators_cluster_active');
            });
        } catch (\Throwable) {
            // Index already exists or cannot be created; ignore to keep migration idempotent.
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('indicators')) {
            return;
        }

        try {
            Schema::table('indicators', function (Blueprint $table) {
                $table->dropIndex('idx_indicators_cluster_active');
            });
        } catch (\Throwable) {
        }

        Schema::table('indicators', function (Blueprint $table) {
            if (Schema::hasColumn('indicators', 'activity_cluster_id')) {
                $table->dropConstrainedForeignId('activity_cluster_id');
            }

            if (Schema::hasColumn('indicators', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }
};
