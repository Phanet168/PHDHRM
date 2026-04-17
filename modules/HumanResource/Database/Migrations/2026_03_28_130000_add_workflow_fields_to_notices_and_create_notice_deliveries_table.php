<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            if (!Schema::hasColumn('notices', 'status')) {
                $table->string('status', 40)->default('draft')->after('notice_by');
            }

            if (!Schema::hasColumn('notices', 'audience_type')) {
                $table->string('audience_type', 40)->default('all')->after('status');
            }

            if (!Schema::hasColumn('notices', 'audience_targets')) {
                $table->json('audience_targets')->nullable()->after('audience_type');
            }

            if (!Schema::hasColumn('notices', 'delivery_channels')) {
                $table->json('delivery_channels')->nullable()->after('audience_targets');
            }

            if (!Schema::hasColumn('notices', 'scheduled_at')) {
                $table->dateTime('scheduled_at')->nullable()->after('delivery_channels');
            }

            if (!Schema::hasColumn('notices', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('scheduled_at');
            }

            if (!Schema::hasColumn('notices', 'approved_at')) {
                $table->dateTime('approved_at')->nullable()->after('approved_by');
            }

            if (!Schema::hasColumn('notices', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('approved_at');
            }

            if (!Schema::hasColumn('notices', 'rejected_at')) {
                $table->dateTime('rejected_at')->nullable()->after('rejected_by');
            }

            if (!Schema::hasColumn('notices', 'rejected_reason')) {
                $table->text('rejected_reason')->nullable()->after('rejected_at');
            }

            if (!Schema::hasColumn('notices', 'sent_by')) {
                $table->unsignedBigInteger('sent_by')->nullable()->after('rejected_reason');
            }

            if (!Schema::hasColumn('notices', 'sent_at')) {
                $table->dateTime('sent_at')->nullable()->after('sent_by');
            }

            if (!Schema::hasColumn('notices', 'delivery_total')) {
                $table->unsignedInteger('delivery_total')->default(0)->after('sent_at');
            }

            if (!Schema::hasColumn('notices', 'delivery_success')) {
                $table->unsignedInteger('delivery_success')->default(0)->after('delivery_total');
            }

            if (!Schema::hasColumn('notices', 'delivery_failed')) {
                $table->unsignedInteger('delivery_failed')->default(0)->after('delivery_success');
            }

            if (!Schema::hasColumn('notices', 'delivery_last_error')) {
                $table->text('delivery_last_error')->nullable()->after('delivery_failed');
            }
        });

        if (!Schema::hasTable('notice_deliveries')) {
            Schema::create('notice_deliveries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('notice_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('channel', 40);
                $table->string('status', 40)->default('queued');
                $table->text('error_message')->nullable();
                $table->json('payload')->nullable();
                $table->dateTime('sent_at')->nullable();
                $table->dateTime('read_at')->nullable();
                $table->timestamps();

                $table->index(['notice_id', 'channel']);
                $table->index(['user_id', 'status']);
                $table->unique(['notice_id', 'user_id', 'channel'], 'notice_deliveries_unique_notice_user_channel');
                $table->foreign('notice_id')->references('id')->on('notices')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notice_deliveries')) {
            Schema::drop('notice_deliveries');
        }

        Schema::table('notices', function (Blueprint $table) {
            $dropColumns = [];

            foreach ([
                'status',
                'audience_type',
                'audience_targets',
                'delivery_channels',
                'scheduled_at',
                'approved_by',
                'approved_at',
                'rejected_by',
                'rejected_at',
                'rejected_reason',
                'sent_by',
                'sent_at',
                'delivery_total',
                'delivery_success',
                'delivery_failed',
                'delivery_last_error',
            ] as $column) {
                if (Schema::hasColumn('notices', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};

