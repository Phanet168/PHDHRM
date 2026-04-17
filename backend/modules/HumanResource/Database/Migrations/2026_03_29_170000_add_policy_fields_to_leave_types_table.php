<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('leave_types')) {
            return;
        }

        Schema::table('leave_types', function (Blueprint $table): void {
            if (!Schema::hasColumn('leave_types', 'leave_type_km')) {
                $table->string('leave_type_km')->nullable()->after('leave_type');
            }

            if (!Schema::hasColumn('leave_types', 'policy_key')) {
                $table->string('policy_key', 40)->nullable()->after('leave_code');
            }

            if (!Schema::hasColumn('leave_types', 'entitlement_scope')) {
                $table->string('entitlement_scope', 40)->default('per_year')->after('policy_key');
            }

            if (!Schema::hasColumn('leave_types', 'entitlement_unit')) {
                $table->string('entitlement_unit', 20)->default('day')->after('entitlement_scope');
            }

            if (!Schema::hasColumn('leave_types', 'entitlement_value')) {
                $table->decimal('entitlement_value', 8, 2)->nullable()->after('entitlement_unit');
            }

            if (!Schema::hasColumn('leave_types', 'max_per_request')) {
                $table->decimal('max_per_request', 8, 2)->nullable()->after('entitlement_value');
            }

            if (!Schema::hasColumn('leave_types', 'is_paid')) {
                $table->boolean('is_paid')->default(true)->after('max_per_request');
            }

            if (!Schema::hasColumn('leave_types', 'requires_attachment')) {
                $table->boolean('requires_attachment')->default(false)->after('is_paid');
            }

            if (!Schema::hasColumn('leave_types', 'requires_medical_certificate')) {
                $table->boolean('requires_medical_certificate')->default(false)->after('requires_attachment');
            }

            if (!Schema::hasColumn('leave_types', 'notes')) {
                $table->text('notes')->nullable()->after('requires_medical_certificate');
            }
        });

        DB::table('leave_types')
            ->whereNull('entitlement_value')
            ->update([
                'entitlement_value' => DB::raw('leave_days'),
            ]);

        $rows = DB::table('leave_types')
            ->select('id', 'leave_type', 'leave_code')
            ->get();

        foreach ($rows as $row) {
            $text = mb_strtolower(trim(((string) ($row->leave_type ?? '')) . ' ' . ((string) ($row->leave_code ?? ''))));
            $update = [];

            if (str_contains($text, 'annual') || str_contains($text, 'ប្រចាំឆ្នាំ')) {
                $update = [
                    'policy_key' => 'annual',
                    'entitlement_scope' => 'per_year',
                    'entitlement_unit' => 'day',
                    'entitlement_value' => 15,
                    'max_per_request' => 15,
                    'is_paid' => 1,
                ];
            } elseif (str_contains($text, 'short') || str_contains($text, 'រយៈពេលខ្លី')) {
                $update = [
                    'policy_key' => 'short',
                    'entitlement_scope' => 'per_request',
                    'entitlement_unit' => 'day',
                    'entitlement_value' => 15,
                    'max_per_request' => 15,
                    'is_paid' => 1,
                ];
            } elseif (str_contains($text, 'sick') || str_contains($text, 'ព្យាបាលជំងឺ')) {
                $update = [
                    'policy_key' => 'sick',
                    'entitlement_scope' => 'per_service_lifetime',
                    'entitlement_unit' => 'month',
                    'entitlement_value' => 12,
                    'max_per_request' => null,
                    'is_paid' => 1,
                    'requires_attachment' => 1,
                    'requires_medical_certificate' => 1,
                ];
            } elseif (str_contains($text, 'maternity') || str_contains($text, 'មាតុភាព')) {
                $update = [
                    'policy_key' => 'maternity',
                    'entitlement_scope' => 'per_request',
                    'entitlement_unit' => 'month',
                    'entitlement_value' => 3,
                    'max_per_request' => 3,
                    'is_paid' => 1,
                ];
            } elseif (
                str_contains($text, 'without pay')
                || str_contains($text, 'unpaid')
                || str_contains($text, 'lwop')
                || str_contains($text, 'គ្មានបៀវត្ស')
                || str_contains($text, 'គ្មានបៀវត្ត')
            ) {
                $update = [
                    'policy_key' => 'unpaid',
                    'entitlement_scope' => 'per_request',
                    'entitlement_unit' => 'day',
                    'is_paid' => 0,
                ];
            }

            if (!empty($update)) {
                DB::table('leave_types')
                    ->where('id', (int) $row->id)
                    ->update($update);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('leave_types')) {
            return;
        }

        Schema::table('leave_types', function (Blueprint $table): void {
            foreach ([
                'notes',
                'requires_medical_certificate',
                'requires_attachment',
                'is_paid',
                'max_per_request',
                'entitlement_value',
                'entitlement_unit',
                'entitlement_scope',
                'policy_key',
                'leave_type_km',
            ] as $column) {
                if (Schema::hasColumn('leave_types', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

