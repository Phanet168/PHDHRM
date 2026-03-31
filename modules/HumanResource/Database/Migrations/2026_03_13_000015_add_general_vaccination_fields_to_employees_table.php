<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'vaccine_name')) {
                $table->text('vaccine_name')->nullable()->after('covid_vaccine_name');
            }
            if (!Schema::hasColumn('employees', 'vaccine_protection')) {
                $table->text('vaccine_protection')->nullable()->after('vaccine_name');
            }
            if (!Schema::hasColumn('employees', 'vaccination_date')) {
                $table->date('vaccination_date')->nullable()->after('covid_vaccine_date');
            }
            if (!Schema::hasColumn('employees', 'vaccination_place')) {
                $table->text('vaccination_place')->nullable()->after('vaccination_date');
            }
        });

        // Backfill from previous COVID-specific fields so existing data remains visible.
        if (Schema::hasColumn('employees', 'covid_vaccine_name') && Schema::hasColumn('employees', 'vaccine_name')) {
            DB::table('employees')
                ->whereNull('vaccine_name')
                ->whereNotNull('covid_vaccine_name')
                ->update([
                    'vaccine_name' => DB::raw('covid_vaccine_name'),
                ]);
        }

        if (Schema::hasColumn('employees', 'covid_vaccine_date') && Schema::hasColumn('employees', 'vaccination_date')) {
            DB::table('employees')
                ->whereNull('vaccination_date')
                ->whereNotNull('covid_vaccine_date')
                ->update([
                    'vaccination_date' => DB::raw('covid_vaccine_date'),
                ]);
        }

        if (Schema::hasColumn('employees', 'vaccine_protection') && Schema::hasColumn('employees', 'vaccine_name')) {
            DB::table('employees')
                ->whereNull('vaccine_protection')
                ->whereNotNull('vaccine_name')
                ->update([
                    'vaccine_protection' => 'COVID-19',
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $columns = [
                'vaccine_name',
                'vaccine_protection',
                'vaccination_date',
                'vaccination_place',
            ];

            $existingColumns = array_filter($columns, static fn (string $column): bool => Schema::hasColumn('employees', $column));
            if (!empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
