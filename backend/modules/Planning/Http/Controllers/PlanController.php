<?php

namespace Modules\Planning\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Modules\Planning\Entities\Account;
use Modules\Planning\Entities\Chapter;
use Modules\Planning\Entities\FundingSource;
use Modules\Planning\Entities\Indicator;
use Modules\Planning\Entities\OrgUnit;
use Modules\Planning\Entities\Plan;
use Modules\Planning\Entities\SubAccount;
use Modules\Planning\Http\Requests\StorePlanCommentRequest;
use Modules\Planning\Http\Requests\StorePlanRequest;
use Modules\Planning\Http\Requests\UpdateActivityPlanRequest;
use Modules\Planning\Http\Requests\UpdateDailyActivityPlanRequest;
use Modules\Planning\Http\Requests\UpdateMicroPlanRequest;
use Modules\Planning\Http\Requests\UpdateMonthlyActivityPlanRequest;
use Modules\Planning\Http\Requests\UpdatePlanRequest;
use Modules\Planning\Http\Requests\WorkflowActionRequest;
use Modules\Planning\Services\PlanFormService;
use Modules\Planning\Services\PlanningAccessService;
use Modules\Planning\Services\PlanningModuleStateService;
use Modules\Planning\Services\PlanningWorkflowService;

class PlanController extends Controller
{
    public function __construct(
        private readonly PlanningAccessService $accessService,
        private readonly PlanFormService $planFormService,
        private readonly PlanningWorkflowService $workflowService,
        private readonly PlanningModuleStateService $moduleStateService
    ) {
    }

    public function index(Request $request): View
    {
        if (!$this->moduleStateService->isInstalled()) {
            return view('planning::setup.index');
        }

        $this->authorize('viewAny', Plan::class);

        $query = $this->accessService->visiblePlansQuery($request->user())
            ->withCount('items')
            ->latest('year')
            ->latest('id');

        if ($request->filled('year')) {
            $query->where('year', (int) $request->string('year'));
        }

        if ($request->filled('status')) {
            $query->where('workflow_status', $request->string('status'));
        }

        if ($request->filled('org_unit_id')) {
            $query->where('org_unit_id', (int) $request->string('org_unit_id'));
        }

        return view('planning::plans.index', [
            'plans' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['year', 'status', 'org_unit_id']),
            'orgUnits' => OrgUnit::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => [
                Plan::STATUS_DRAFT,
                Plan::STATUS_SUBMITTED,
                Plan::STATUS_APPROVED,
                Plan::STATUS_REJECTED,
                Plan::STATUS_CONSOLIDATED,
            ],
        ]);
    }

    public function create(): View
    {
        if (!$this->moduleStateService->isInstalled()) {
            return view('planning::setup.index');
        }

        $this->authorize('create', Plan::class);

        return view('planning::plans.create', $this->formData([
            'plan' => new Plan([
                'year' => now()->year,
                'period_type' => 'annual',
                'plan_type' => 'micro',
            ]),
        ]));
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        $orgUnit = $this->accessService->currentOrgUnit($request->user());
        abort_if($orgUnit === null, 422, 'មិនទាន់មានអង្គភាពភ្ជាប់ជាមួយអ្នកប្រើប្រាស់នេះទេ។');

        $plan = $this->planFormService->store($request->validated(), $request->user(), $orgUnit->id);

        return redirect()
            ->route('planning.plans.micro-plan.edit', $plan)
            ->with('success', 'បានរក្សាទុកជំហានទី១ រួចហើយ។ សូមបន្តទៅជំហានទី២ ដើម្បីរៀបចំសកម្មភាព និងថវិកា។');
    }

    public function show(Plan $plan): View
    {
        if (!$this->moduleStateService->isInstalled()) {
            return view('planning::setup.index');
        }

        $this->authorize('view', $plan);

        $plan->load([
            'orgUnit',
            'items.responsibleOrgUnit',
            'items.indicators.indicator.activityCluster.subProgram.program',
            'comments.user',
            'comments.orgUnit',
            'attachments',
        ]);

        return view('planning::plans.show', [
            'plan' => $plan,
        ]);
    }

    public function edit(Plan $plan): View
    {
        if (!$this->moduleStateService->isInstalled()) {
            return view('planning::setup.index');
        }

        $this->authorize('update', $plan);

        $plan->load(['orgUnit', 'items.indicators', 'attachments']);

        return view('planning::plans.edit', $this->formData([
            'plan' => $plan,
        ]));
    }

    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan = $this->planFormService->update($plan, $request->validated(), $request->user());

        return redirect()
            ->route('planning.plans.micro-plan.edit', $plan)
            ->with('success', 'បានរក្សាទុកជំហានទី១ រួចហើយ។ សូមបន្តទៅជំហានទី២ ដើម្បីបញ្ចូលសកម្មភាព និងថវិកា។');
    }

    public function microPlan(Plan $plan): View
    {
        if (!$this->moduleStateService->isInstalled()) {
            return view('planning::setup.index');
        }

        $this->authorize('update', $plan);

        $plan->load([
            'orgUnit',
            'items.indicators.indicator.activityCluster.subProgram.program',
            'items.costs.fundingSource',
        ]);

        $indicatorItems = $plan->items
            ->where('item_type', 'indicator_result')
            ->values();

        $activityItems = $plan->items
            ->where('item_type', 'activity')
            ->values();

        return view('planning::plans.micro-plan', [
            'plan' => $plan,
            'orgUnits' => OrgUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'chapters' => Chapter::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get(['id', 'code', 'name']),
            'accounts' => Account::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get(['id', 'chapter_id', 'code', 'name']),
            'subAccounts' => SubAccount::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get(['id', 'account_id', 'code', 'name']),
            'fundingSources' => FundingSource::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'indicatorItems' => $indicatorItems,
            'activityItems' => $activityItems,
        ]);
    }

    public function updateMicroPlan(UpdateMicroPlanRequest $request, Plan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $this->planFormService->syncMicroPlan($plan, $request->validated()['activities'] ?? [], $request->user());

        return redirect()
            ->route('planning.plans.activity-plan.edit', $plan)
            ->with('success', 'បានរក្សាទុកជំហានទី២ រួចហើយ។ សូមបន្តទៅជំហានទី៣ ដើម្បីកំណត់ត្រីមាសអនុវត្តសកម្មភាព។');
    }

    public function activityPlan(Plan $plan): View
    {
        if (!$this->moduleStateService->isInstalled()) {
            return view('planning::setup.index');
        }

        $this->authorize('update', $plan);

        $plan->load([
            'orgUnit',
            'items.responsibleOrgUnit',
            'items.indicators.indicator.activityCluster.subProgram.program',
            'items.costs',
            'items.schedules',
        ]);

        return view('planning::plans.activity-plan', [
            'plan' => $plan,
            'activityItems' => $plan->items->where('item_type', 'activity')->values(),
        ]);
    }

    public function updateActivityPlan(UpdateActivityPlanRequest $request, Plan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $this->planFormService->syncActivityPlanSchedules($plan, $request->validated()['activities'] ?? []);

        return redirect()
            ->route('planning.plans.monthly-activity-plan.edit', $plan)
            ->with('success', 'បានរក្សាទុកជំហានទី៣ រួចហើយ។ សូមបន្តទៅជំហានទី៤ ដើម្បីកំណត់ខែអនុវត្តសកម្មភាព។');
    }

    public function monthlyActivityPlan(Plan $plan): View
    {
        if (!$this->moduleStateService->isInstalled()) {
            return view('planning::setup.index');
        }

        $this->authorize('update', $plan);

        $plan->load([
            'orgUnit',
            'items.responsibleOrgUnit',
            'items.indicators.indicator.activityCluster.subProgram.program',
            'items.costs',
            'items.schedules',
        ]);

        return view('planning::plans.monthly-activity-plan', [
            'plan' => $plan,
            'activityItems' => $plan->items->where('item_type', 'activity')->values(),
        ]);
    }

    public function updateMonthlyActivityPlan(UpdateMonthlyActivityPlanRequest $request, Plan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $this->planFormService->syncMonthlyActivityPlanSchedules($plan, $request->validated()['activities'] ?? []);

        return redirect()
            ->route('planning.plans.daily-activity-plan.edit', $plan)
            ->with('success', 'បានរក្សាទុកជំហានទី៤រួចហើយ។ សូមបន្តទៅជំហានទី៥ ដើម្បីកំណត់ថ្ងៃអនុវត្តសកម្មភាព។');
    }

    public function dailyActivityPlan(Plan $plan): View
    {
        if (!$this->moduleStateService->isInstalled()) {
            return view('planning::setup.index');
        }

        $this->authorize('update', $plan);

        $plan->load([
            'orgUnit',
            'items.responsibleOrgUnit',
            'items.indicators.indicator.activityCluster.subProgram.program',
            'items.costs',
            'items.schedules',
        ]);

        return view('planning::plans.daily-activity-plan', [
            'plan' => $plan,
            'activityItems' => $plan->items->where('item_type', 'activity')->values(),
        ]);
    }

    public function updateDailyActivityPlan(UpdateDailyActivityPlanRequest $request, Plan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $this->planFormService->syncDailyActivityPlanSchedules($plan, $request->validated()['activities'] ?? []);

        return redirect()
            ->route('planning.plans.show', $plan)
            ->with('success', 'បានបញ្ចប់ជំហានទី៥ និងរក្សាទុកផែនការប្រចាំថ្ងៃរួចហើយ។');
    }

    public function submit(WorkflowActionRequest $request, Plan $plan): RedirectResponse
    {
        $this->authorize('submit', $plan);

        $this->workflowService->submit($plan, $request->user(), $request->string('comment')->toString());

        return back()->with('success', 'បានដាក់ស្នើផែនការសម្រេចបានសម្រាប់ពិនិត្យរួចហើយ។');
    }

    public function review(WorkflowActionRequest $request, Plan $plan): RedirectResponse
    {
        $this->authorize('review', $plan);

        $this->workflowService->review($plan, $request->user(), $request->string('comment')->toString());

        return back()->with('success', 'បានរក្សាទុកការពិនិត្យរួចហើយ។');
    }

    public function approve(WorkflowActionRequest $request, Plan $plan): RedirectResponse
    {
        $this->authorize('approve', $plan);

        $this->workflowService->approve($plan, $request->user(), $request->string('comment')->toString());

        return back()->with('success', 'បានអនុម័តផែនការ។');
    }

    public function reject(WorkflowActionRequest $request, Plan $plan): RedirectResponse
    {
        $this->authorize('reject', $plan);

        $request->validate(['comment' => ['required', 'string']]);

        $this->workflowService->reject($plan, $request->user(), $request->string('comment')->toString());

        return back()->with('success', 'បានបដិសេធផែនការ ហើយអាចកែប្រែឡើងវិញបាន។');
    }

    public function consolidate(WorkflowActionRequest $request, Plan $plan): RedirectResponse
    {
        $this->authorize('consolidate', $plan);

        $this->workflowService->consolidate($plan, $request->user(), $request->string('comment')->toString());

        return back()->with('success', 'បានបូកសរុបផែនការរួចហើយ។');
    }

    public function comment(StorePlanCommentRequest $request, Plan $plan): RedirectResponse
    {
        $this->planFormService->addComment(
            $plan,
            $request->user(),
            $this->accessService->currentOrgUnit($request->user())?->id,
            $request->string('comment')->toString()
        );

        return back()->with('success', 'បានបន្ថែមមតិយោបល់។');
    }

    public function attachment(Plan $plan, int $attachmentId)
    {
        $this->authorize('view', $plan);

        $attachment = $plan->attachments()->findOrFail($attachmentId);

        return Storage::disk($attachment->disk)->download($attachment->file_path, $attachment->original_name);
    }

    public function export(Plan $plan)
    {
        $this->authorize('export', $plan);

        $plan->load(['orgUnit', 'items.responsibleOrgUnit', 'items.indicators.indicator', 'items.costs.fundingSource']);

        $rows = collect();
        foreach ($plan->items as $item) {
            $indicatorRow = $item->indicators->first();
            if ($item->item_type === 'indicator_result') {
                $rows->push([
                    'Section' => 'Achievement Plan',
                    'Plan Title' => $plan->title,
                    'Unit' => $plan->orgUnit?->name,
                    'Year' => $plan->year,
                    'Created At' => optional($item->created_at)->format('Y-m-d H:i:s'),
                    'Indicator' => $indicatorRow?->indicator?->name,
                    'Responsible Unit' => $item->responsibleOrgUnit?->name,
                    'Previous Year Achievement' => $indicatorRow?->baseline_value,
                    'Target' => $indicatorRow?->target_value,
                    'Current Achievement' => $indicatorRow?->achieved_value,
                    'Activity' => null,
                    'Cost Name' => null,
                    'Total Cost' => null,
                ]);
                continue;
            }

            foreach ($item->costs as $cost) {
                $rows->push([
                    'Section' => 'Micro Plan',
                    'Plan Title' => $plan->title,
                    'Unit' => $plan->orgUnit?->name,
                    'Year' => $plan->year,
                    'Created At' => optional($item->created_at)->format('Y-m-d H:i:s'),
                    'Indicator' => $indicatorRow?->indicator?->name,
                    'Responsible Unit' => $item->responsibleOrgUnit?->name,
                    'Previous Year Achievement' => null,
                    'Target' => null,
                    'Current Achievement' => null,
                    'Activity' => $item->title,
                    'Cost Name' => $cost->cost_name,
                    'Total Cost' => $cost->total_cost,
                ]);
            }
        }

        $headers = $rows->first() ? array_keys($rows->first()) : ['Plan Title'];
        $filename = 'planning-' . $plan->uuid . '.csv';

        $callback = function () use ($headers, $rows) {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, $headers);
            foreach ($rows as $row) {
                fputcsv($stream, $row);
            }
            fclose($stream);
        };

        return Response::streamDownload($callback, $filename, ['Content-Type' => 'text/csv']);
    }

    private function formData(array $data = []): array
    {
        $plan = $data['plan'];

        return $data + [
            'orgUnits' => OrgUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'indicators' => Indicator::query()
                ->with('activityCluster.subProgram.program')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get(['id', 'activity_cluster_id', 'code', 'name', 'name_km', 'unit_of_measure']),
            'currentOrgUnit' => $plan->exists
                ? $plan->orgUnit
                : (request()->user() ? $this->accessService->currentOrgUnit(request()->user()) : null),
        ];
    }
}
