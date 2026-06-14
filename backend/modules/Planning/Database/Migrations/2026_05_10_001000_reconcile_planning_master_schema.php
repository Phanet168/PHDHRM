<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('org_units')) {
            Schema::table('org_units', function (Blueprint $table) {
                if (!Schema::hasColumn('org_units', 'org_path_code')) {
                    $table->string('org_path_code')->nullable()->after('hierarchy_path');
                }

                if (!Schema::hasColumn('org_units', 'manager_name')) {
                    $table->string('manager_name')->nullable()->after('org_path_code');
                }
            });
        }

        foreach (['programs', 'sub_programs', 'activity_clusters'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'sort_order')) {
                    $table->unsignedInteger('sort_order')->default(1)->after('description');
                }
            });

            DB::table($tableName)
                ->whereNull('sort_order')
                ->update(['sort_order' => 1]);
        }

        if (Schema::hasTable('chart_of_accounts')) {
            Schema::table('chart_of_accounts', function (Blueprint $table) {
                if (!Schema::hasColumn('chart_of_accounts', 'chapter_name')) {
                    $table->string('chapter_name')->nullable()->after('chapter_code');
                }

                if (!Schema::hasColumn('chart_of_accounts', 'account_name')) {
                    $table->string('account_name')->nullable()->after('account_code');
                }

                if (!Schema::hasColumn('chart_of_accounts', 'subaccount_name')) {
                    $table->string('subaccount_name')->nullable()->after('subaccount_code');
                }

                if (!Schema::hasColumn('chart_of_accounts', 'expense_type')) {
                    $table->string('expense_type', 100)->nullable()->after('subaccount_name');
                }
            });

            DB::statement("
                UPDATE chart_of_accounts
                SET
                    chapter_name = COALESCE(NULLIF(chapter_name, ''), chapter_code),
                    account_name = COALESCE(NULLIF(account_name, ''), name),
                    subaccount_name = COALESCE(NULLIF(subaccount_name, ''), name)
            ");
        }

        if (!Schema::hasTable('indicators')) {
            Schema::create('indicators', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('name_km')->nullable();
                $table->string('unit_of_measure', 100)->nullable();
                $table->enum('value_type', ['number', 'percentage', 'currency', 'text'])->default('number');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('indicators')) {
            Schema::dropIfExists('indicators');
        }

        if (Schema::hasTable('chart_of_accounts')) {
            Schema::table('chart_of_accounts', function (Blueprint $table) {
                foreach (['chapter_name', 'account_name', 'subaccount_name', 'expense_type'] as $column) {
                    if (Schema::hasColumn('chart_of_accounts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        foreach (['programs', 'sub_programs', 'activity_clusters'] as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'sort_order')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }

        if (Schema::hasTable('org_units')) {
            Schema::table('org_units', function (Blueprint $table) {
                foreach (['org_path_code', 'manager_name'] as $column) {
                    if (Schema::hasColumn('org_units', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
