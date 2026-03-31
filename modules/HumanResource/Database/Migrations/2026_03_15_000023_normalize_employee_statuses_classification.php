<?php

use Illuminate\Database\Migrations\Migration;
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

        $setTransitionGroup = function (array $names, string $group): void {
            foreach ($names as $name) {
                DB::table('employee_statuses')
                    ->where(function ($query) use ($name) {
                        $query->where('name_km', $name)
                            ->orWhere('name_en', $name);
                    })
                    ->update([
                        'transition_group' => $group,
                        'updated_at' => now(),
                    ]);
            }
        };

        // Reclassify statuses that were previously mapped to the wrong group.
        $setTransitionGroup([
            'បោះបង់ការងារ',
            'មរណៈភាព',
            'បាត់បង់សម្បទាវិជ្ជាជីវៈ',
            'លុបដោយឈប់ហួសកំណត់នៃការទំនេរគ្មានបៀវត្ស',
            'លុបដោយមានកំហុស',
        ], 'inactive');

        $setTransitionGroup([
            'ទំនេរគ្មានបៀវត្យតាមសំណើរបស់សាមីជន',
        ], 'suspended');

        // Transfer-out statuses should be treated as event-only and hidden from selection.
        $eventOnlyStatuses = [
            'ផ្ទេរបុគ្គលិកចេញពីខេត្តទៅថ្នាក់កណ្តាល',
            'ផ្ទេរបុគ្គលិកចេញពីខេត្តទៅក្រសួងផ្សេង',
            'ផ្ទេរបុគ្គលិកចេញពីខេត្តទៅខេត្តផ្សេង',
            'ផ្ទេរបុគ្គលិកចេញពីក្រសួងទៅក្រសួងផ្សេង',
            'ផ្ទេរបុគ្គលិកចេញពីថ្នាក់កណ្តាលទៅខេត្ត',
        ];

        foreach ($eventOnlyStatuses as $name) {
            DB::table('employee_statuses')
                ->where(function ($query) use ($name) {
                    $query->where('name_km', $name)
                        ->orWhere('name_en', $name);
                })
                ->update([
                    'is_active' => 0,
                    'updated_at' => now(),
                ]);
        }

        // Unknown statuses should not be used for new transitions.
        DB::table('employee_statuses')
            ->where(function ($query) {
                $query->where('name_km', 'មិនស្គាល់ពីស្ថានភាព')
                    ->orWhere('name_en', 'មិនស្គាល់ពីស្ថានភាព');
            })
            ->update([
                'is_active' => 0,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('employee_statuses')) {
            return;
        }

        // Restore only activity flags that were intentionally disabled here.
        $reactivateStatuses = [
            'ផ្ទេរបុគ្គលិកចេញពីខេត្តទៅថ្នាក់កណ្តាល',
            'ផ្ទេរបុគ្គលិកចេញពីខេត្តទៅក្រសួងផ្សេង',
            'ផ្ទេរបុគ្គលិកចេញពីខេត្តទៅខេត្តផ្សេង',
            'ផ្ទេរបុគ្គលិកចេញពីក្រសួងទៅក្រសួងផ្សេង',
            'ផ្ទេរបុគ្គលិកចេញពីថ្នាក់កណ្តាលទៅខេត្ត',
            'មិនស្គាល់ពីស្ថានភាព',
        ];

        foreach ($reactivateStatuses as $name) {
            DB::table('employee_statuses')
                ->where(function ($query) use ($name) {
                    $query->where('name_km', $name)
                        ->orWhere('name_en', $name);
                })
                ->update([
                    'is_active' => 1,
                    'updated_at' => now(),
                ]);
        }

        // Keep transition_group corrections intact on rollback to avoid reintroducing incorrect classification.
    }
};
