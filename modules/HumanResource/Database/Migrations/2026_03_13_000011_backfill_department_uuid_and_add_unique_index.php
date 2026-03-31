<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('departments')
            ->where(function ($query) {
                $query->whereNull('uuid')->orWhere('uuid', '');
            })
            ->orderBy('id')
            ->select('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('departments')
                        ->where('id', $row->id)
                        ->update([
                            'uuid' => (string) Str::uuid(),
                        ]);
                }
            });

        $duplicateUuids = DB::table('departments')
            ->select('uuid')
            ->whereNotNull('uuid')
            ->where('uuid', '!=', '')
            ->groupBy('uuid')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('uuid');

        foreach ($duplicateUuids as $uuid) {
            $ids = DB::table('departments')
                ->where('uuid', $uuid)
                ->orderBy('id')
                ->pluck('id');

            $keepFirst = true;
            foreach ($ids as $id) {
                if ($keepFirst) {
                    $keepFirst = false;
                    continue;
                }

                DB::table('departments')
                    ->where('id', $id)
                    ->update([
                        'uuid' => (string) Str::uuid(),
                    ]);
            }
        }

        if (!$this->hasIndex('departments', 'departments_uuid_unique')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->unique('uuid', 'departments_uuid_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->hasIndex('departments', 'departments_uuid_unique')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->dropUnique('departments_uuid_unique');
            });
        }
    }

    protected function hasIndex(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

        return !empty($indexes);
    }
};

