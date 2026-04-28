<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\HumanResource\Entities\Notice;
use Modules\HumanResource\Support\NoticeDispatchService;

class DispatchScheduledNotices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notice:dispatch-scheduled {--limit=50 : Maximum number of notices to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch approved/scheduled notices that are due for delivery';

    /**
     * Execute the console command.
     */
    public function handle(NoticeDispatchService $dispatchService): int
    {
        $limit = (int) $this->option('limit');
        $limit = max(1, min(500, $limit));

        $notices = Notice::query()
            ->withoutGlobalScope('sortByLatest')
            ->whereIn('status', [
                Notice::STATUS_APPROVED,
                Notice::STATUS_SCHEDULED,
                Notice::STATUS_PARTIAL_FAILED,
            ])
            ->whereNull('sent_at')
            ->where(function ($query): void {
                $query->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($notices->isEmpty()) {
            $this->info('No due notices found.');
            return self::SUCCESS;
        }

        $sentCount = 0;
        $partialCount = 0;

        foreach ($notices as $notice) {
            $summary = $dispatchService->deliver($notice);

            $status = Notice::STATUS_SENT;
            if ((int) ($summary['failed'] ?? 0) > 0) {
                $status = Notice::STATUS_PARTIAL_FAILED;
                $partialCount++;
            } else {
                $sentCount++;
            }

            $notice->update([
                'status' => $status,
                'sent_by' => $notice->sent_by ?: $notice->approved_by,
                'sent_at' => now(),
                'delivery_total' => (int) ($summary['total'] ?? 0),
                'delivery_success' => (int) ($summary['success'] ?? 0),
                'delivery_failed' => (int) ($summary['failed'] ?? 0),
                'delivery_last_error' => $summary['last_error'] ?? null,
            ]);
        }

        $this->info(sprintf(
            'Processed %d notices. sent=%d, partial_failed=%d',
            $notices->count(),
            $sentCount,
            $partialCount
        ));

        return self::SUCCESS;
    }
}
