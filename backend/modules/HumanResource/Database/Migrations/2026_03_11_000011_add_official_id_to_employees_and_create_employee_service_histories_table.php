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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('official_id_10', 10)->nullable()->after('employee_code');
        });

        // Backfill official_id_10 from card_no where value is a clean 10-digit code.
        $seen = [];
        DB::table('employees')
            ->select(['id', 'card_no', 'official_id_10'])
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$seen) {
                foreach ($rows as $row) {
                    if (!empty($row->official_id_10)) {
                        $seen[(string) $row->official_id_10] = true;
                        continue;
                    }

                    $cardNo = trim((string) ($row->card_no ?? ''));
                    if ($cardNo === '' || !preg_match('/^\d{10}$/', $cardNo)) {
                        continue;
                    }

                    if (isset($seen[$cardNo])) {
                        continue;
                    }

                    DB::table('employees')
                        ->where('id', $row->id)
                        ->update(['official_id_10' => $cardNo]);

                    $seen[$cardNo] = true;
                }
            });

        Schema::table('employees', function (Blueprint $table) {
            $table->unique('official_id_10', 'employees_official_id_10_unique');
        });

        Schema::create('employee_service_histories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('event_type', 60);
            $table->date('event_date')->nullable();
            $table->string('title', 191);
            $table->text('details')->nullable();
            $table->text('from_value')->nullable();
            $table->text('to_value')->nullable();
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('metadata')->nullable();
            $table->updateCreatedBy();
            $table->timestamps();

            $table->index(['employee_id', 'event_date'], 'employee_service_histories_employee_date_idx');
            $table->index('event_type', 'employee_service_histories_event_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_service_histories');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_official_id_10_unique');
            $table->dropColumn('official_id_10');
        });
    }
};
