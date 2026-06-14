<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chapters')) {
            Schema::create('chapters', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('code', 20)->unique();
                $table->string('name');
                $table->string('name_km')->nullable();
                $table->unsignedInteger('sort_order')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('chapter_id')->constrained('chapters')->cascadeOnDelete();
                $table->string('code', 20);
                $table->string('name');
                $table->string('name_km')->nullable();
                $table->unsignedInteger('sort_order')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['chapter_id', 'code'], 'uq_accounts_chapter_code');
            });
        }

        if (!Schema::hasTable('sub_accounts')) {
            Schema::create('sub_accounts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
                $table->string('code', 20);
                $table->string('name');
                $table->string('name_km')->nullable();
                $table->unsignedInteger('sort_order')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['account_id', 'code'], 'uq_sub_accounts_account_code');
            });
        }

        if (Schema::hasTable('chart_of_accounts')) {
            $chapterRows = DB::table('chart_of_accounts')
                ->select('chapter_code as code', 'chapter_name as name')
                ->distinct()
                ->orderBy('chapter_code')
                ->get();

            foreach ($chapterRows as $index => $chapterRow) {
                DB::table('chapters')->updateOrInsert(
                    ['code' => $chapterRow->code],
                    [
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'name' => $chapterRow->name ?: $chapterRow->code,
                        'sort_order' => $index + 1,
                        'is_active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            $accountRows = DB::table('chart_of_accounts')
                ->select('chapter_code', 'account_code as code', 'account_name as name')
                ->distinct()
                ->orderBy('chapter_code')
                ->orderBy('account_code')
                ->get();

            foreach ($accountRows as $index => $accountRow) {
                $chapterId = DB::table('chapters')->where('code', $accountRow->chapter_code)->value('id');
                if (!$chapterId) {
                    continue;
                }

                DB::table('accounts')->updateOrInsert(
                    ['chapter_id' => $chapterId, 'code' => $accountRow->code],
                    [
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'name' => $accountRow->name ?: $accountRow->code,
                        'sort_order' => $index + 1,
                        'is_active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            $subAccountRows = DB::table('chart_of_accounts')
                ->select('chapter_code', 'account_code', 'subaccount_code as code', 'subaccount_name as name', 'name_km', 'is_active')
                ->distinct()
                ->orderBy('chapter_code')
                ->orderBy('account_code')
                ->orderBy('subaccount_code')
                ->get();

            foreach ($subAccountRows as $index => $subAccountRow) {
                $chapterId = DB::table('chapters')->where('code', $subAccountRow->chapter_code)->value('id');
                if (!$chapterId) {
                    continue;
                }

                $accountId = DB::table('accounts')
                    ->where('chapter_id', $chapterId)
                    ->where('code', $subAccountRow->account_code)
                    ->value('id');

                if (!$accountId) {
                    continue;
                }

                DB::table('sub_accounts')->updateOrInsert(
                    ['account_id' => $accountId, 'code' => $subAccountRow->code],
                    [
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'name' => $subAccountRow->name ?: $subAccountRow->code,
                        'name_km' => $subAccountRow->name_km,
                        'sort_order' => $index + 1,
                        'is_active' => (bool) $subAccountRow->is_active,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        if (Schema::hasTable('plan_item_costs')) {
            Schema::table('plan_item_costs', function (Blueprint $table) {
                if (!Schema::hasColumn('plan_item_costs', 'chapter_id')) {
                    $table->foreignId('chapter_id')->nullable()->after('chapter_code')->constrained('chapters')->nullOnDelete();
                }

                if (!Schema::hasColumn('plan_item_costs', 'account_id')) {
                    $table->foreignId('account_id')->nullable()->after('account_code')->constrained('accounts')->nullOnDelete();
                }

                if (!Schema::hasColumn('plan_item_costs', 'sub_account_id')) {
                    $table->foreignId('sub_account_id')->nullable()->after('subaccount_code')->constrained('sub_accounts')->nullOnDelete();
                }
            });

            $costRows = DB::table('plan_item_costs')->select('id', 'chapter_code', 'account_code', 'subaccount_code')->get();
            foreach ($costRows as $costRow) {
                $chapterId = DB::table('chapters')->where('code', $costRow->chapter_code)->value('id');
                $accountId = $chapterId
                    ? DB::table('accounts')->where('chapter_id', $chapterId)->where('code', $costRow->account_code)->value('id')
                    : null;
                $subAccountId = $accountId
                    ? DB::table('sub_accounts')->where('account_id', $accountId)->where('code', $costRow->subaccount_code)->value('id')
                    : null;

                DB::table('plan_item_costs')
                    ->where('id', $costRow->id)
                    ->update([
                        'chapter_id' => $chapterId,
                        'account_id' => $accountId,
                        'sub_account_id' => $subAccountId,
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('plan_item_costs')) {
            Schema::table('plan_item_costs', function (Blueprint $table) {
                foreach (['sub_account_id', 'account_id', 'chapter_id'] as $column) {
                    if (Schema::hasColumn('plan_item_costs', $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }
            });
        }

        Schema::dropIfExists('sub_accounts');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('chapters');
    }
};
