<?php

namespace Modules\Report\Http\Controllers\Health;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Report\Entities\ReportAction;
use Modules\Report\Entities\ReportDepartment;
use Modules\Report\Entities\ReportIndicator;
use Modules\Report\Services\PeriodCode;
use Modules\Report\Services\XlsxExport;

class IssueController extends HealthController
{
    public function index(Request $request)
    {
        $base = $this->query($request);
        $counts = (clone $base)->reorder()->selectRaw('action_status, COUNT(*) as n')->groupBy('action_status')->pluck('n', 'action_status');

        return view('report::health.issues', [
            'actions' => $base->with(['indicator.unit', 'indicator.department', 'period'])->paginate(30)->withQueryString(),
            'counts' => $counts,
            'statuses' => ReportAction::STATUSES,
            'years' => $this->years(),
            'departments' => ReportDepartment::with('units')->orderBy('sort_order')->orderBy('id')->get(),
            'indicators' => ReportIndicator::where('active', true)->ordered()->get(['id', 'code', 'name', 'unit_id', 'owner_user_id']),
            'p' => $this->periodFromRequest($request),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $indicator = ReportIndicator::findOrFail($data['indicator_id']);
        abort_unless($this->access->canEditIndicator($request->user(), $indicator), 403);

        $period = $this->period($data['period']);
        $action = ReportAction::create($this->fields($data) + [
            'period_id' => $period->id,
            'indicator_id' => $indicator->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return $this->respond($request, $action, 'បានបន្ថែមបញ្ហាប្រឈម');
    }

    public function update(Request $request, ReportAction $issue)
    {
        abort_unless($this->access->canEditIndicator($request->user(), $issue->indicator), 403);
        $data = $this->validated($request, false);
        $issue->update($this->fields($data) + ['updated_by' => $request->user()->id]);

        return $this->respond($request, $issue, 'បានកែប្រែបញ្ហាប្រឈម');
    }

    public function destroy(Request $request, ReportAction $issue)
    {
        abort_unless($this->access->canEditIndicator($request->user(), $issue->indicator), 403);
        $issue->delete();

        return back()->with('success', 'បានលុបបញ្ហាប្រឈម');
    }

    public function export(Request $request)
    {
        $rows = $this->query($request)->with(['indicator.unit', 'indicator.department', 'period'])->get();

        return XlsxExport::download(
            'issues_' . now()->format('Ymd') . '.xlsx',
            'បញ្ហាប្រឈម',
            ['រយៈពេល', 'ID', 'សូចនាករ', 'ជំពូក', 'ផ្នែក', 'បញ្ហា', 'សកម្មភាព', 'អ្នកទទួលខុសត្រូវ', 'កាលកំណត់', 'ស្ថានភាព', 'ដំណោះស្រាយ', 'ថ្ងៃដោះស្រាយ'],
            $rows->map(fn (ReportAction $a) => [
                $a->period?->label, $a->indicator?->code, $a->indicator?->name, $a->indicator?->department?->name,
                $a->indicator?->unit?->name, $a->issue_text, $a->action_text, $a->action_owner,
                $a->action_due?->format('Y-m-d'), $a->status_label, $a->resolution_text, $a->resolution_date?->format('Y-m-d'),
            ])->all(),
            [16, 10, 50, 26, 26, 40, 40, 22, 14, 16, 40, 14]
        );
    }

    /** Filtered issue list, ordered department → unit → indicator → due date (as in PHDReport). */
    private function query(Request $request): Builder
    {
        $q = trim((string) $request->input('q'));

        return ReportAction::query()
            ->select('report_actions.*')
            ->join('report_indicators as i', 'i.id', '=', 'report_actions.indicator_id')
            ->join('report_periods as p', 'p.id', '=', 'report_actions.period_id')
            ->when($request->filled('year'), fn ($b) => $b->where('p.year', (int) $request->input('year')))
            ->when($request->filled('period'), fn ($b) => $b->where('p.code', $request->input('period')))
            ->when($request->filled('status'), fn ($b) => $b->where('report_actions.action_status', $request->input('status')))
            ->when($request->filled('department'), fn ($b) => $b->where('i.department_id', (int) $request->input('department')))
            ->when($request->filled('unit'), fn ($b) => $b->where('i.unit_id', (int) $request->input('unit')))
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w
                ->where('i.name', 'like', "%{$q}%")->orWhere('i.code', $q)
                ->orWhere('report_actions.issue_text', 'like', "%{$q}%")
                ->orWhere('report_actions.action_text', 'like', "%{$q}%")
                ->orWhere('report_actions.action_owner', 'like', "%{$q}%")))
            ->orderBy('i.department_id')->orderBy('i.unit_id')->orderByRaw('LENGTH(i.code)')->orderBy('i.code')
            ->orderByRaw('report_actions.action_due IS NULL')->orderBy('report_actions.action_due');
    }

    private function validated(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'period' => [$creating ? 'required' : 'nullable', 'string', 'max:16'],
            'indicator_id' => [$creating ? 'required' : 'nullable', 'integer'],
            'issue_text' => ['nullable', 'string', 'max:5000'],
            'action_text' => ['nullable', 'string', 'max:5000'],
            'action_owner' => ['nullable', 'string', 'max:255'],
            'action_due' => ['nullable', 'date'],
            'action_status' => ['required', Rule::in(array_keys(ReportAction::STATUSES))],
            'resolution_text' => ['nullable', 'string', 'max:5000'],
            'resolution_date' => ['nullable', 'date'],
        ]);
    }

    private function fields(array $data): array
    {
        $done = $data['action_status'] === 'done';

        return [
            'issue_text' => $data['issue_text'] ?? null,
            'action_text' => $data['action_text'] ?? null,
            'action_owner' => $data['action_owner'] ?? null,
            'action_due' => $data['action_due'] ?? null,
            'action_status' => $data['action_status'],
            'resolution_text' => $done ? ($data['resolution_text'] ?? null) : null,
            'resolution_date' => $done ? ($data['resolution_date'] ?? now()->toDateString()) : null,
        ];
    }

    private function respond(Request $request, ReportAction $action, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'action' => $action->fresh()->append('status_label')]);
        }

        return back()->with('success', $message . ' • ' . PeriodCode::label($action->period->code));
    }
}
