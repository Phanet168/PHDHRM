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
        if (!Schema::hasTable('employee_statuses')) {
            return;
        }

        Schema::table('employee_statuses', function (Blueprint $table) {
            $table->string('transition_group', 20)
                ->default('active')
                ->after('name_en');

            $table->index('transition_group', 'employee_statuses_transition_group_idx');
        });

        DB::table('employee_statuses')->orderBy('id')->chunk(200, function ($rows) {
            foreach ($rows as $row) {
                $name = mb_strtolower(trim((string) (($row->name_km ?: '') . ' ' . ($row->name_en ?: ''))));
                $group = 'active';

                if ($this->containsAny($name, [
                    'លាឈប់',
                    'និវត្តន៍',
                    'មរណភាព',
                    'ស្លាប់',
                    'បណ្តេញចេញ',
                    'បញ្ឈប់',
                    'inactive',
                    'retire',
                    'deceased',
                    'terminated',
                    'resigned',
                    'dismissed',
                    'removed',
                ])) {
                    $group = 'inactive';
                } elseif ($this->containsAny($name, [
                    'ផ្អាក',
                    'ទំនេរគ្មានបៀវត្ស',
                    'គ្មានបៀវត្ស',
                    'suspend',
                    'suspended',
                    'without pay',
                    'study leave',
                ])) {
                    $group = 'suspended';
                }

                DB::table('employee_statuses')
                    ->where('id', $row->id)
                    ->update(['transition_group' => $group]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('employee_statuses')) {
            return;
        }

        Schema::table('employee_statuses', function (Blueprint $table) {
            $table->dropIndex('employee_statuses_transition_group_idx');
            $table->dropColumn('transition_group');
        });
    }

    private function containsAny(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_strpos($value, mb_strtolower($needle)) !== false) {
                return true;
            }
        }

        return false;
    }
};
