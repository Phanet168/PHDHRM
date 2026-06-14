<?php

namespace Modules\Planning\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Planning\Entities\ChartOfAccount;
use Modules\Planning\Entities\Indicator;
use Modules\Planning\Entities\PlanItemCost;
use Modules\Planning\Entities\Plan;
use Modules\Planning\Entities\PlanAttachment;
use Modules\Planning\Entities\PlanComment;
use Modules\Planning\Entities\PlanItem;
use Modules\Planning\Entities\PlanItemIndicator;
use Modules\Planning\Entities\PlanItemSchedule;
use Modules\Planning\Entities\SubAccount;

class PlanFormService
{
    public function store(array $payload, User $user, int $orgUnitId): Plan
    {
        return DB::transaction(function () use ($payload, $user, $orgUnitId) {
            $scope = $this->resolvePlanningScope($payload['items']);

            $plan = Plan::create([
                'org_unit_id' => $orgUnitId,
                'program_id' => $scope['program_id'],
                'sub_program_id' => $scope['sub_program_id'],
                'activity_cluster_id' => $scope['activity_cluster_id'],
                'plan_type' => 'micro',
                'title' => $payload['title'] ?: $this->defaultTitle($payload['year'], $scope['indicator_name']),
                'reference_no' => $payload['reference_no'] ?? null,
                'year' => $payload['year'],
                'period_type' => 'annual',
                'period_no' => null,
                'objective' => $payload['objective'] ?? null,
                'summary' => $payload['summary'] ?? null,
                'background' => $payload['background'] ?? null,
                'assumptions' => $payload['assumptions'] ?? null,
                'workflow_status' => Plan::STATUS_DRAFT,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->syncItems($plan, $payload['items'] ?? []);
            $this->syncAttachments($plan, $payload['attachments'] ?? [], $user);
            $this->syncComment($plan, $payload['submission_note'] ?? null, $user, $orgUnitId);
            $this->refreshPlanTotals($plan);

            return $plan->fresh(['orgUnit', 'items.indicators.indicator', 'comments', 'attachments']);
        });
    }

    public function update(Plan $plan, array $payload, User $user): Plan
    {
        return DB::transaction(function () use ($plan, $payload, $user) {
            $scope = $this->resolvePlanningScope($payload['items']);

            $plan->forceFill([
                'program_id' => $scope['program_id'],
                'sub_program_id' => $scope['sub_program_id'],
                'activity_cluster_id' => $scope['activity_cluster_id'],
                'plan_type' => 'micro',
                'title' => $payload['title'] ?: $this->defaultTitle($payload['year'], $scope['indicator_name']),
                'reference_no' => $payload['reference_no'] ?? null,
                'year' => $payload['year'],
                'period_type' => 'annual',
                'period_no' => null,
                'objective' => $payload['objective'] ?? null,
                'summary' => $payload['summary'] ?? null,
                'background' => $payload['background'] ?? null,
                'assumptions' => $payload['assumptions'] ?? null,
                'updated_by' => $user->id,
                'is_locked' => false,
            ])->save();

            $plan->items()->each(function (PlanItem $item) {
                $item->indicators()->delete();
            });
            $plan->items()->delete();

            $this->syncItems($plan, $payload['items'] ?? []);
            $this->syncAttachments($plan, $payload['attachments'] ?? [], $user);
            $this->syncComment($plan, $payload['submission_note'] ?? null, $user, $plan->org_unit_id);
            $this->refreshPlanTotals($plan);

            return $plan->fresh(['orgUnit', 'items.indicators.indicator', 'comments', 'attachments']);
        });
    }

    public function addComment(Plan $plan, User $user, ?int $orgUnitId, string $comment): void
    {
        PlanComment::create([
            'plan_id' => $plan->id,
            'org_unit_id' => $orgUnitId,
            'user_id' => $user->id,
            'comment_type' => 'general',
            'comment' => $comment,
        ]);
    }

    public function syncMicroPlan(Plan $plan, array $activities, User $user): Plan
    {
        return DB::transaction(function () use ($plan, $activities, $user) {
            $indicatorSource = $plan->items()
                ->where('item_type', 'indicator_result')
                ->with('indicators')
                ->get()
                ->keyBy(fn (PlanItem $item) => optional($item->indicators->first())->indicator_id);

            $plan->items()
                ->where('item_type', 'activity')
                ->get()
                ->each(function (PlanItem $item) {
                    $item->costs()->delete();
                    $item->indicators()->delete();
                    $item->delete();
                });

            foreach (array_values($activities) as $index => $activityPayload) {
                $indicator = Indicator::query()
                    ->with('activityCluster.subProgram.program')
                    ->findOrFail($activityPayload['indicator_id']);

                $cluster = $indicator->activityCluster;
                $subProgram = $cluster?->subProgram;
                $program = $subProgram?->program;
                abort_if($cluster === null || $subProgram === null || $program === null, 422, 'សូចនាករមិនទាន់ភ្ជាប់ទៅចង្កោមសកម្មភាពពេញលេញទេ។');

                $item = PlanItem::create([
                    'plan_id' => $plan->id,
                    'program_id' => $program->id,
                    'sub_program_id' => $subProgram->id,
                    'activity_cluster_id' => $cluster->id,
                    'responsible_org_unit_id' => $activityPayload['responsible_org_unit_id'],
                    'item_code' => $activityPayload['item_code'] ?? null,
                    'title' => $activityPayload['title'],
                    'description' => $activityPayload['description'] ?? null,
                    'item_type' => 'activity',
                    'indicator_text' => $indicator->name,
                    'indicator' => $indicator->name,
                    'target_unit' => $indicator->unit_of_measure,
                    'item_year' => $plan->year,
                    'sort_order' => $index + 1,
                    'total_cost' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $sourceIndicatorItem = $indicatorSource->get($indicator->id);
                $sourceIndicatorRow = $sourceIndicatorItem?->indicators?->first();

                PlanItemIndicator::create([
                    'plan_item_id' => $item->id,
                    'indicator_id' => $indicator->id,
                    'baseline_value' => $sourceIndicatorRow?->baseline_value,
                    'target_value' => $sourceIndicatorRow?->target_value,
                    'achieved_value' => $sourceIndicatorRow?->achieved_value,
                    'value_text' => $sourceIndicatorRow?->value_text ?: $indicator->unit_of_measure,
                    'note' => 'Linked from achievement plan',
                ]);

                $itemTotal = $this->syncActivityCosts($item, $activityPayload['costs'] ?? []);
                $item->forceFill(['total_cost' => $itemTotal, 'updated_at' => now()])->save();
            }

            $this->cleanupOrphanActivities($plan);
            $this->refreshPlanTotals($plan);

            return $plan->fresh(['items.indicators.indicator', 'items.costs']);
        });
    }

    public function syncActivityPlanSchedules(Plan $plan, array $activities): Plan
    {
        return DB::transaction(function () use ($plan, $activities) {
            $items = $plan->items()
                ->where('item_type', 'activity')
                ->get()
                ->keyBy('id');

            foreach (array_values($activities) as $activityPayload) {
                $item = $items->get((int) ($activityPayload['item_id'] ?? 0));
                if (!$item) {
                    continue;
                }

                $item->schedules()->delete();

                $quarters = collect($activityPayload['quarters'] ?? [])
                    ->map(fn ($quarter) => (int) $quarter)
                    ->filter(fn ($quarter) => in_array($quarter, [1, 2, 3, 4], true))
                    ->unique()
                    ->sort()
                    ->values();

                foreach ($quarters as $quarter) {
                    PlanItemSchedule::create([
                        'plan_item_id' => $item->id,
                        'quarter' => $quarter,
                        'period_no' => $quarter,
                        'period_type' => 'quarterly',
                        'planned_quantity' => $activityPayload['planned_quantity'] ?? null,
                        'period_label' => $activityPayload['period_label'] ?? null,
                        'note' => $activityPayload['note'] ?? null,
                    ]);
                }
            }

            return $plan->fresh(['items.schedules', 'items.indicators.indicator']);
        });
    }

    public function syncMonthlyActivityPlanSchedules(Plan $plan, array $activities): Plan
    {
        return DB::transaction(function () use ($plan, $activities) {
            $items = $plan->items()
                ->where('item_type', 'activity')
                ->with('schedules')
                ->get()
                ->keyBy('id');

            $quarterMonthMap = [
                1 => [1, 2, 3],
                2 => [4, 5, 6],
                3 => [7, 8, 9],
                4 => [10, 11, 12],
            ];

            foreach (array_values($activities) as $activityPayload) {
                $item = $items->get((int) ($activityPayload['item_id'] ?? 0));
                if (!$item) {
                    continue;
                }

                $allowedMonths = $item->schedules
                    ->where('period_type', 'quarterly')
                    ->pluck('quarter')
                    ->filter()
                    ->flatMap(fn ($quarter) => $quarterMonthMap[(int) $quarter] ?? [])
                    ->unique()
                    ->sort()
                    ->values();

                $item->schedules()
                    ->where('period_type', 'monthly')
                    ->delete();

                $months = collect($activityPayload['months'] ?? [])
                    ->map(fn ($month) => (int) $month)
                    ->filter(fn ($month) => $allowedMonths->contains($month))
                    ->unique()
                    ->sort()
                    ->values();

                foreach ($months as $month) {
                    PlanItemSchedule::create([
                        'plan_item_id' => $item->id,
                        'month' => $month,
                        'period_no' => $month,
                        'period_type' => 'monthly',
                        'note' => $activityPayload['note'] ?? null,
                    ]);
                }
            }

            return $plan->fresh(['items.schedules', 'items.indicators.indicator']);
        });
    }

    public function syncDailyActivityPlanSchedules(Plan $plan, array $activities): Plan
    {
        return DB::transaction(function () use ($plan, $activities) {
            $items = $plan->items()
                ->where('item_type', 'activity')
                ->with('schedules')
                ->get()
                ->keyBy('id');

            foreach (array_values($activities) as $activityPayload) {
                $item = $items->get((int) ($activityPayload['item_id'] ?? 0));
                if (!$item) {
                    continue;
                }

                $allowedMonths = $item->schedules
                    ->where('period_type', 'monthly')
                    ->pluck('month')
                    ->filter()
                    ->map(fn ($month) => (int) $month)
                    ->unique()
                    ->sort()
                    ->values();

                $item->schedules()
                    ->where('period_type', 'daily')
                    ->delete();

                $dates = collect($activityPayload['dates'] ?? [])
                    ->filter()
                    ->map(fn ($date) => \Illuminate\Support\Carbon::parse($date))
                    ->filter(fn ($date) => (int) $date->year === (int) $plan->year && $allowedMonths->contains((int) $date->month))
                    ->unique(fn ($date) => $date->toDateString())
                    ->sortBy(fn ($date) => $date->toDateString())
                    ->values();

                foreach ($dates as $date) {
                    PlanItemSchedule::create([
                        'plan_item_id' => $item->id,
                        'month' => (int) $date->month,
                        'period_no' => (int) $date->day,
                        'period_type' => 'daily',
                        'start_date' => $date->toDateString(),
                        'end_date' => $date->toDateString(),
                        'period_label' => $date->format('d/m/Y'),
                        'activity_task_text' => $item->title,
                        'goal_text' => $activityPayload['goal_text'] ?? null,
                        'expected_result_text' => null,
                        'verification_text' => null,
                        'note' => $activityPayload['note'] ?? null,
                    ]);
                }
            }

            return $plan->fresh(['items.schedules', 'items.indicators.indicator']);
        });
    }

    private function syncItems(Plan $plan, array $items): void
    {
        foreach (array_values($items) as $index => $itemPayload) {
            $indicator = Indicator::query()->findOrFail($itemPayload['indicator_id']);
            $cluster = $indicator->activityCluster()->with('subProgram.program')->first();
            $subProgram = $cluster?->subProgram;
            $program = $subProgram?->program;

            abort_if($cluster === null || $subProgram === null || $program === null, 422, 'សូចនាករមិនទាន់ភ្ជាប់ទៅចង្កោមសកម្មភាពពេញលេញទេ។');

            $item = PlanItem::create([
                'plan_id' => $plan->id,
                'program_id' => $program->id,
                'sub_program_id' => $subProgram->id,
                'activity_cluster_id' => $cluster->id,
                'responsible_org_unit_id' => $itemPayload['responsible_org_unit_id'],
                'item_code' => $itemPayload['item_code'] ?? null,
                'title' => $itemPayload['title'] ?: $indicator->name,
                'description' => $itemPayload['description'] ?? null,
                'item_type' => 'indicator_result',
                'indicator_text' => $indicator->name,
                'indicator' => $indicator->name,
                'target_text' => isset($itemPayload['target_value']) ? (string) $itemPayload['target_value'] : null,
                'target' => isset($itemPayload['target_value']) ? (string) $itemPayload['target_value'] : null,
                'target_unit' => $itemPayload['target_unit'] ?? $indicator->unit_of_measure,
                'item_year' => $plan->year,
                'item_quarter' => null,
                'sort_order' => $index + 1,
                'total_cost' => 0,
            ]);

            PlanItemIndicator::create([
                'plan_item_id' => $item->id,
                'indicator_id' => $indicator->id,
                'baseline_value' => $itemPayload['baseline_value'] ?? null,
                'target_value' => $itemPayload['target_value'] ?? null,
                'achieved_value' => $itemPayload['achieved_value'] ?? null,
                'value_text' => $itemPayload['target_unit'] ?? $indicator->unit_of_measure,
                'note' => $itemPayload['indicator_note'] ?? null,
            ]);
        }
    }

    private function syncActivityCosts(PlanItem $item, array $costs): float
    {
        $itemTotal = 0;

        foreach (array_values($costs) as $costPayload) {
            $subAccount = SubAccount::query()->with('account.chapter')->findOrFail($costPayload['sub_account_id']);
            $account = $subAccount->account;
            $chapter = $account?->chapter;

            $chartOfAccount = ChartOfAccount::query()
                ->where('chapter_code', $chapter?->code)
                ->where('account_code', $account?->code)
                ->where('subaccount_code', $subAccount->code)
                ->firstOrFail();

            $qty = (float) ($costPayload['qty'] ?? 0);
            $implementerCount = (float) ($costPayload['implementer_count'] ?? 1);
            $occurrenceCount = (float) ($costPayload['occurrence_count'] ?? 1);
            $unitPrice = (float) ($costPayload['unit_price'] ?? 0);
            $totalCost = round($qty * $implementerCount * $occurrenceCount * $unitPrice, 2);
            $itemTotal += $totalCost;

            PlanItemCost::create([
                'plan_item_id' => $item->id,
                'chapter_id' => $chapter?->id,
                'account_id' => $account?->id,
                'sub_account_id' => $subAccount->id,
                'chart_of_account_id' => $chartOfAccount->id,
                'funding_source_id' => $costPayload['funding_source_id'] ?? null,
                'cost_code' => $subAccount->code,
                'cost_name' => $subAccount->name,
                'chapter_code' => $chartOfAccount->chapter_code,
                'account_code' => $chartOfAccount->account_code,
                'subaccount_code' => $chartOfAccount->subaccount_code,
                'qty' => $qty,
                'implementer_count' => $implementerCount,
                'occurrence_count' => $occurrenceCount,
                'unit' => $costPayload['unit'] ?? null,
                'unit_price' => $unitPrice,
                'currency_code' => $costPayload['currency_code'] ?? 'KHR',
                'total_cost' => $totalCost,
                'note' => $costPayload['note'] ?? null,
            ]);
        }

        return $itemTotal;
    }

    /**
     * @param array<int, UploadedFile> $attachments
     */
    private function syncAttachments(Plan $plan, array $attachments, User $user): void
    {
        $disk = (string) config('planning.attachment_disk', 'public');
        $directory = trim((string) config('planning.attachment_directory', 'planning/attachments'), '/');

        foreach ($attachments as $attachment) {
            if (!$attachment instanceof UploadedFile) {
                continue;
            }

            $storedPath = $attachment->store($directory . '/' . $plan->uuid, $disk);

            PlanAttachment::create([
                'plan_id' => $plan->id,
                'uploaded_by' => $user->id,
                'disk' => $disk,
                'file_path' => $storedPath,
                'original_name' => $attachment->getClientOriginalName(),
                'mime_type' => $attachment->getClientMimeType(),
                'file_size' => $attachment->getSize() ?: 0,
            ]);
        }
    }

    private function syncComment(Plan $plan, ?string $comment, User $user, ?int $orgUnitId): void
    {
        $comment = trim((string) $comment);
        if ($comment === '') {
            return;
        }

        PlanComment::create([
            'plan_id' => $plan->id,
            'org_unit_id' => $orgUnitId,
            'user_id' => $user->id,
            'comment_type' => 'submission_note',
            'comment' => $comment,
        ]);
    }

    private function refreshPlanTotals(Plan $plan): void
    {
        $totalEstimatedCost = $plan->items()
            ->where('item_type', 'activity')
            ->sum('total_cost');

        $plan->forceFill([
            'total_estimated_cost' => $totalEstimatedCost,
            'total_revenue_amount' => 0,
        ])->save();
    }

    private function cleanupOrphanActivities(Plan $plan): void
    {
        $plan->items()
            ->where('item_type', 'activity')
            ->with('indicators')
            ->get()
            ->filter(fn (PlanItem $item) => $item->indicators->isEmpty())
            ->each(function (PlanItem $item) {
                $item->schedules()->delete();
                $item->costs()->delete();
                $item->indicators()->delete();
                $item->delete();
            });
    }

    private function resolvePlanningScope(array $items): array
    {
        $firstIndicatorId = data_get($items, '0.indicator_id');
        $indicator = Indicator::query()
            ->with('activityCluster.subProgram.program')
            ->findOrFail($firstIndicatorId);

        $cluster = $indicator->activityCluster;
        $subProgram = $cluster?->subProgram;
        $program = $subProgram?->program;

        abort_if($cluster === null || $subProgram === null || $program === null, 422, 'សូចនាករមិនទាន់ភ្ជាប់ទៅចង្កោមសកម្មភាពពេញលេញទេ។');

        return [
            'program_id' => $program->id,
            'sub_program_id' => $subProgram->id,
            'activity_cluster_id' => $cluster->id,
            'indicator_name' => $indicator->name,
        ];
    }

    private function defaultTitle(int $year, string $indicatorName): string
    {
        return 'ផែនការសម្រេចបាន ឆ្នាំ ' . $year . ' - ' . $indicatorName;
    }
}
