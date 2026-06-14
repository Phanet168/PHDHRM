<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plans')) {
            Schema::table('plans', function (Blueprint $table) {
                if (!Schema::hasColumn('plans', 'program_id')) {
                    $table->foreignId('program_id')->nullable()->after('org_unit_id')->constrained('programs')->nullOnDelete();
                }

                if (!Schema::hasColumn('plans', 'sub_program_id')) {
                    $table->foreignId('sub_program_id')->nullable()->after('program_id')->constrained('sub_programs')->nullOnDelete();
                }

                if (!Schema::hasColumn('plans', 'activity_cluster_id')) {
                    $table->foreignId('activity_cluster_id')->nullable()->after('sub_program_id')->constrained('activity_clusters')->nullOnDelete();
                }

                if (!Schema::hasColumn('plans', 'period_no')) {
                    $table->unsignedTinyInteger('period_no')->nullable()->after('period_type');
                }

                if (!Schema::hasColumn('plans', 'workflow_status')) {
                    $table->string('workflow_status', 50)->nullable()->after('period_no');
                }

                if (!Schema::hasColumn('plans', 'background')) {
                    $table->text('background')->nullable()->after('summary');
                }

                if (!Schema::hasColumn('plans', 'assumptions')) {
                    $table->text('assumptions')->nullable()->after('background');
                }

                if (!Schema::hasColumn('plans', 'total_personnel_cost')) {
                    $table->decimal('total_personnel_cost', 16, 2)->default(0)->after('total_estimated_cost');
                }

                if (!Schema::hasColumn('plans', 'total_revenue_amount')) {
                    $table->decimal('total_revenue_amount', 16, 2)->default(0)->after('total_personnel_cost');
                }

                if (!Schema::hasColumn('plans', 'reviewed_at')) {
                    $table->timestamp('reviewed_at')->nullable()->after('approved_by');
                }

                if (!Schema::hasColumn('plans', 'reviewed_by')) {
                    $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at');
                }
            });

            DB::statement("
                UPDATE plans
                SET
                    period_no = COALESCE(period_no, quarter),
                    workflow_status = COALESCE(NULLIF(workflow_status, ''), status)
            ");

            if (Schema::hasColumn('plans', 'period_type')) {
                DB::statement("
                    ALTER TABLE plans
                    MODIFY period_type ENUM('annual','semi_annual','quarterly','monthly') NOT NULL DEFAULT 'annual'
                ");
            }

            $planIds = DB::table('plan_items')->distinct()->pluck('plan_id');

            foreach ($planIds as $planId) {
                $summary = DB::table('plan_items')
                    ->where('plan_id', $planId)
                    ->selectRaw('COUNT(DISTINCT program_id) as program_count')
                    ->selectRaw('MIN(program_id) as program_id')
                    ->selectRaw('COUNT(DISTINCT sub_program_id) as sub_program_count')
                    ->selectRaw('MIN(sub_program_id) as sub_program_id')
                    ->selectRaw('COUNT(DISTINCT activity_cluster_id) as activity_cluster_count')
                    ->selectRaw('MIN(activity_cluster_id) as activity_cluster_id')
                    ->first();

                if (!$summary) {
                    continue;
                }

                $updates = [];

                if ((int) $summary->program_count === 1) {
                    $updates['program_id'] = $summary->program_id;
                }

                if ((int) $summary->sub_program_count === 1) {
                    $updates['sub_program_id'] = $summary->sub_program_id;
                }

                if ((int) $summary->activity_cluster_count === 1) {
                    $updates['activity_cluster_id'] = $summary->activity_cluster_id;
                }

                if ($updates !== []) {
                    DB::table('plans')
                        ->where('id', $planId)
                        ->where(function ($query) {
                            $query
                                ->whereNull('program_id')
                                ->orWhereNull('sub_program_id')
                                ->orWhereNull('activity_cluster_id');
                        })
                        ->update($updates);
                }
            }
        }

        if (Schema::hasTable('plan_items')) {
            Schema::table('plan_items', function (Blueprint $table) {
                if (!Schema::hasColumn('plan_items', 'item_code')) {
                    $table->string('item_code')->nullable()->after('responsible_org_unit_id');
                }

                if (!Schema::hasColumn('plan_items', 'item_type')) {
                    $table->string('item_type', 50)->default('activity')->after('description');
                }

                if (!Schema::hasColumn('plan_items', 'indicator_text')) {
                    $table->string('indicator_text')->nullable()->after('item_type');
                }

                if (!Schema::hasColumn('plan_items', 'target_text')) {
                    $table->string('target_text')->nullable()->after('indicator_text');
                }

                if (!Schema::hasColumn('plan_items', 'item_period_no')) {
                    $table->unsignedTinyInteger('item_period_no')->nullable()->after('item_year');
                }

                if (!Schema::hasColumn('plan_items', 'planned_quantity')) {
                    $table->decimal('planned_quantity', 14, 2)->nullable()->after('period_label');
                }

                if (!Schema::hasColumn('plan_items', 'planned_unit')) {
                    $table->string('planned_unit', 100)->nullable()->after('planned_quantity');
                }
            });

            DB::statement("
                UPDATE plan_items
                SET
                    indicator_text = COALESCE(indicator_text, indicator),
                    target_text = COALESCE(target_text, target),
                    item_period_no = COALESCE(item_period_no, item_quarter)
            ");
        }

        if (Schema::hasTable('plan_item_schedules')) {
            Schema::table('plan_item_schedules', function (Blueprint $table) {
                if (!Schema::hasColumn('plan_item_schedules', 'period_no')) {
                    $table->unsignedTinyInteger('period_no')->nullable()->after('month');
                }

                if (!Schema::hasColumn('plan_item_schedules', 'period_type')) {
                    $table->string('period_type', 50)->nullable()->after('period_no');
                }
            });

            DB::statement("
                UPDATE plan_item_schedules
                SET
                    period_no = COALESCE(period_no, quarter, month),
                    period_type = COALESCE(
                        period_type,
                        CASE
                            WHEN month IS NOT NULL THEN 'monthly'
                            WHEN quarter IS NOT NULL THEN 'quarterly'
                            ELSE NULL
                        END
                    )
            ");
        }

        if (Schema::hasTable('plan_item_costs')) {
            Schema::table('plan_item_costs', function (Blueprint $table) {
                if (!Schema::hasColumn('plan_item_costs', 'cost_code')) {
                    $table->string('cost_code')->nullable()->after('funding_source_id');
                }

                if (!Schema::hasColumn('plan_item_costs', 'qty')) {
                    $table->decimal('qty', 14, 2)->nullable()->after('subaccount_code');
                }

                if (!Schema::hasColumn('plan_item_costs', 'unit_price')) {
                    $table->decimal('unit_price', 14, 2)->nullable()->after('unit');
                }
            });

            DB::statement("
                UPDATE plan_item_costs
                SET
                    qty = COALESCE(qty, quantity),
                    unit_price = COALESCE(unit_price, unit_cost)
            ");
        }

        if (!Schema::hasTable('plan_item_indicators')) {
            Schema::create('plan_item_indicators', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('plan_item_id')->constrained('plan_items')->cascadeOnDelete();
                $table->foreignId('indicator_id')->constrained('indicators')->restrictOnDelete();
                $table->decimal('baseline_value', 14, 2)->nullable();
                $table->decimal('target_value', 14, 2)->nullable();
                $table->decimal('achieved_value', 14, 2)->nullable();
                $table->string('value_text')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['plan_item_id', 'indicator_id'], 'idx_plan_item_indicators_item_indicator');
            });
        }

        if (!Schema::hasTable('plan_personnel_lines')) {
            Schema::create('plan_personnel_lines', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('plan_item_id')->constrained('plan_items')->cascadeOnDelete();
                $table->string('cadre_name');
                $table->string('cadre_name_km')->nullable();
                $table->unsignedInteger('person_count')->default(0);
                $table->unsignedInteger('days_count')->default(0);
                $table->decimal('unit_cost', 14, 2)->default(0);
                $table->decimal('total_cost', 16, 2)->default(0);
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['plan_item_id'], 'idx_plan_personnel_lines_item');
            });
        }

        if (!Schema::hasTable('plan_revenue_lines')) {
            Schema::create('plan_revenue_lines', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
                $table->string('revenue_code')->nullable();
                $table->string('revenue_name');
                $table->foreignId('funding_source_id')->nullable()->constrained('funding_sources')->nullOnDelete();
                $table->decimal('quantity', 14, 2)->nullable();
                $table->string('unit', 100)->nullable();
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('total_amount', 16, 2)->default(0);
                $table->text('note')->nullable();
                $table->unsignedInteger('sort_order')->default(1);
                $table->timestamps();

                $table->index(['plan_id', 'sort_order'], 'idx_plan_revenue_lines_plan_sort');
            });
        }
    }

    public function down(): void
    {
        foreach (['plan_revenue_lines', 'plan_personnel_lines', 'plan_item_indicators'] as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::dropIfExists($tableName);
            }
        }

        if (Schema::hasTable('plan_item_costs')) {
            Schema::table('plan_item_costs', function (Blueprint $table) {
                foreach (['cost_code', 'qty', 'unit_price'] as $column) {
                    if (Schema::hasColumn('plan_item_costs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('plan_item_schedules')) {
            Schema::table('plan_item_schedules', function (Blueprint $table) {
                foreach (['period_no', 'period_type'] as $column) {
                    if (Schema::hasColumn('plan_item_schedules', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('plan_items')) {
            Schema::table('plan_items', function (Blueprint $table) {
                foreach (['item_code', 'item_type', 'indicator_text', 'target_text', 'item_period_no', 'planned_quantity', 'planned_unit'] as $column) {
                    if (Schema::hasColumn('plan_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('plans')) {
            Schema::table('plans', function (Blueprint $table) {
                foreach ([
                    'program_id',
                    'sub_program_id',
                    'activity_cluster_id',
                    'period_no',
                    'workflow_status',
                    'background',
                    'assumptions',
                    'total_personnel_cost',
                    'total_revenue_amount',
                    'reviewed_at',
                    'reviewed_by',
                ] as $column) {
                    if (Schema::hasColumn('plans', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });

            DB::statement("
                ALTER TABLE plans
                MODIFY period_type ENUM('annual','quarterly','monthly') NOT NULL DEFAULT 'annual'
            ");
        }
    }
};
