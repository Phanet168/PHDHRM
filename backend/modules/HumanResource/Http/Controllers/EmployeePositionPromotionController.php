<?php

namespace Modules\HumanResource\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\EmployeeProfileExtra;
use Modules\HumanResource\Entities\EmployeeServiceHistory;
use Modules\HumanResource\Entities\EmployeeUnitPosting;
use Modules\HumanResource\Entities\EmployeeWorkHistory;
use Modules\HumanResource\Entities\Position;
use Modules\HumanResource\Support\EmployeeServiceHistoryService;
use Modules\HumanResource\Support\OrgUnitRuleService;

class EmployeePositionPromotionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:read_employee'])->only('index', 'export');
        $this->middleware(['permission:update_employee'])->only('store');
        $this->middleware(['isAdmin', 'permission:update_employee'])->only('update');
        $this->middleware(['isAdmin', 'permission:delete_employee'])->only('destroy');
    }

    public function index(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $year = (int) $request->query('year', now()->year);
        if ($year < 1950 || $year > 2100) {
            $year = (int) now()->year;
        }

        $managedBranchIds = $this->managedBranchIds($orgUnitRuleService);

        $employees = Employee::query()
            ->where('is_active', true)
            ->with(['position', 'department', 'sub_department'])
            ->orderBy('last_name')
            ->orderBy('first_name');
        $this->applyManagedBranchScope($employees, $managedBranchIds);
        $employees = $employees->get();

        $promotions = EmployeeUnitPosting::query()
            ->with([
                'employee.position',
                'employee.department',
                'employee.sub_department',
                'position',
            ])
            ->whereNotNull('position_id')
            ->whereYear('start_date', $year)
            ->where('note', 'like', '[POSITION_PROMOTION]%')
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if (is_array($managedBranchIds)) {
            $promotions->whereHas('employee', function ($q) use ($managedBranchIds) {
                $q->whereIn('department_id', $managedBranchIds)
                    ->orWhereIn('sub_department_id', $managedBranchIds);
            });
        }

        $promotions = $promotions->get();

        return view('humanresource::employee.position-promotion.index', [
            'year' => $year,
            'employees' => $employees,
            'positions' => Position::query()->where('is_active', true)->orderBy('position_name')->get(),
            'current_position_labels' => $this->currentPositionLabels($employees),
            'previous_position_labels' => $this->previousPositionLabelMap($promotions),
            'promotion_documents' => $this->promotionDocumentMap($promotions, $year),
            'promotions' => $promotions,
        ]);
    }

    public function export(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $year = (int) $request->query('year', now()->year);
        if ($year < 1950 || $year > 2100) {
            $year = (int) now()->year;
        }

        $managedBranchIds = $this->managedBranchIds($orgUnitRuleService);

        $promotions = EmployeeUnitPosting::query()
            ->with([
                'employee.department',
                'employee.sub_department',
                'position',
            ])
            ->whereNotNull('position_id')
            ->whereYear('start_date', $year)
            ->where('note', 'like', '[POSITION_PROMOTION]%')
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if (is_array($managedBranchIds)) {
            $promotions->whereHas('employee', function ($q) use ($managedBranchIds) {
                $q->whereIn('department_id', $managedBranchIds)
                    ->orWhereIn('sub_department_id', $managedBranchIds);
            });
        }

        $promotions = $promotions->get();
        $previousPositionLabels = $this->previousPositionLabelMap($promotions);
        $promotionDocuments = $this->promotionDocumentMap($promotions, $year);

        $filename = 'position-promotions-' . $year . '.csv';

        return response()->streamDownload(function () use ($promotions, $previousPositionLabels, $promotionDocuments) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'ល.រ',
                'អត្តលេខមន្ត្រី',
                'ឈ្មោះ',
                'អង្គភាព',
                'តួនាទីចាស់',
                'តួនាទីថ្មី',
                'ថ្ងៃមានប្រសិទ្ធិភាព',
                'លេខលិខិត',
                'ថ្ងៃខែលិខិត',
                'សម្គាល់',
            ]);

            $row = 1;
            foreach ($promotions as $promotion) {
                $unit = $promotion->employee?->sub_department?->department_name
                    ?: ($promotion->employee?->department?->department_name ?: '-');
                $newPosition = $promotion->position?->position_name_km
                    ?: ($promotion->position?->position_name ?: '-');
                $oldPosition = $previousPositionLabels[(int) $promotion->id] ?? '-';
                $note = trim(str_replace('[POSITION_PROMOTION] |', '', (string) $promotion->note));

                fputcsv($handle, [
                    $row++,
                    $promotion->employee?->employee_id ?: '-',
                    $this->normalizeKhmerText($promotion->employee?->full_name ?: '-'),
                    $this->normalizeKhmerText($unit),
                    $this->normalizeKhmerText($oldPosition),
                    $this->normalizeKhmerText($newPosition),
                    optional($promotion->start_date)->format('Y-m-d') ?: '-',
                    $promotionDocuments[(int) $promotion->id]['document_reference'] ?? '-',
                    $promotionDocuments[(int) $promotion->id]['document_date'] ?? '-',
                    $this->normalizeKhmerText($note !== '' ? $note : '-'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function store(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'position_id' => 'required|exists:positions,id',
            'effective_date' => 'required|date',
            'document_reference' => 'nullable|string|max:191',
            'document_date' => 'nullable|date',
            'note' => 'nullable|string',
        ]);

        $employee = Employee::query()->where('id', (int) $validated['employee_id'])->firstOrFail();
        $this->assertCanManageEmployee($employee, $orgUnitRuleService);

        $newPosition = Position::query()
            ->where('id', (int) $validated['position_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $effectiveDate = Carbon::parse($validated['effective_date'])->toDateString();
        $documentDate = !empty($validated['document_date'])
            ? Carbon::parse($validated['document_date'])->toDateString()
            : null;

        $oldPositionId = (int) ($employee->position_id ?? 0);
        if ($oldPositionId === (int) $newPosition->id) {
            Toastr::info('តួនាទីថ្មីដូចតួនាទីបច្ចុប្បន្ន (មិនមានការកែប្រែ)។', 'ព័ត៌មាន');
            return redirect()->route('employee-position-promotions.index', [
                'year' => Carbon::parse($effectiveDate)->year,
            ]);
        }

        $activePrimaryPosting = EmployeeUnitPosting::query()
            ->where('employee_id', $employee->id)
            ->where('is_primary', true)
            ->whereNull('end_date')
            ->latest('id')
            ->first();

        $targetDepartmentId = (int) ($employee->sub_department_id ?: $employee->department_id);
        if (!$targetDepartmentId && $activePrimaryPosting) {
            $targetDepartmentId = (int) $activePrimaryPosting->department_id;
        }

        if ($targetDepartmentId <= 0) {
            return redirect()->back()
                ->withErrors(['employee_id' => 'រកមិនឃើញអង្គភាពបច្ចុប្បន្នរបស់មន្ត្រី។'])
                ->withInput();
        }

        DB::beginTransaction();
        try {
            if ($activePrimaryPosting) {
                $activePrimaryPosting->is_primary = false;
                if (!$activePrimaryPosting->end_date || Carbon::parse($activePrimaryPosting->end_date)->gte($effectiveDate)) {
                    $candidateEndDate = Carbon::parse($effectiveDate)->subDay()->toDateString();
                    $startDate = optional($activePrimaryPosting->start_date)->toDateString();
                    $activePrimaryPosting->end_date = ($startDate && $candidateEndDate < $startDate)
                        ? $effectiveDate
                        : $candidateEndDate;
                }
                $activePrimaryPosting->save();
            }

            $oldPositionLabel = $this->positionNameById($oldPositionId) ?: '-';
            $newPositionLabel = $newPosition->position_name_km ?: $newPosition->position_name;
            $normalizedNote = trim((string) ($validated['note'] ?? ''));

            $postingNoteParts = [
                '[POSITION_PROMOTION]',
                'Old: ' . $oldPositionLabel,
                'New: ' . $newPositionLabel,
            ];
            if (!empty($validated['document_reference'])) {
                $postingNoteParts[] = 'Doc: ' . $validated['document_reference'];
            }
            if (!empty($documentDate)) {
                $postingNoteParts[] = 'DocDate: ' . $documentDate;
            }
            if ($normalizedNote !== '') {
                $postingNoteParts[] = $normalizedNote;
            }

            $newPosting = EmployeeUnitPosting::create([
                'employee_id' => $employee->id,
                'department_id' => $targetDepartmentId,
                'position_id' => $newPosition->id,
                'start_date' => $effectiveDate,
                'end_date' => null,
                'is_primary' => true,
                'note' => implode(' | ', $postingNoteParts),
            ]);

            $employee->position_id = $newPosition->id;
            $employee->save();

            EmployeeProfileExtra::updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'current_position_start_date' => $effectiveDate,
                    'current_position_document_number' => $validated['document_reference'] ?: null,
                    'current_position_document_date' => $documentDate,
                ]
            );

            $workHistoryNoteParts = [
                'តួនាទីចាស់: ' . $oldPositionLabel,
                'តួនាទីថ្មី: ' . $newPositionLabel,
            ];
            if ($normalizedNote !== '') {
                $workHistoryNoteParts[] = $normalizedNote;
            }

            EmployeeWorkHistory::create([
                'employee_id' => $employee->id,
                'work_status_name' => 'ឡើងតួនាទី',
                'start_date' => $effectiveDate,
                'document_reference' => $validated['document_reference'] ?: null,
                'document_date' => $documentDate,
                'note' => implode(' | ', $workHistoryNoteParts),
            ]);

            $this->historyService()->log(
                $employee->id,
                'position_change',
                'Position changed',
                "Changed position from {$oldPositionLabel} to {$newPositionLabel}",
                $effectiveDate,
                $oldPositionLabel,
                $newPositionLabel,
                'employee_unit_posting',
                $newPosting->id,
                [
                    'document_reference' => $validated['document_reference'] ?: null,
                    'document_date' => $documentDate,
                ]
            );

            DB::commit();
            Toastr::success('បានកែប្រែតួនាទី និងកត់ត្រាប្រវត្តិការងាររួចរាល់។', 'ជោគជ័យ');

            return redirect()->route('employee-position-promotions.index', [
                'year' => Carbon::parse($effectiveDate)->year,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            activity()
                ->causedBy(auth()->user())
                ->log('An error occurred: ' . $e->getMessage());

            Toastr::error('រក្សាទុកការឡើងតួនាទីមិនបានជោគជ័យ។', 'បរាជ័យ');
            return redirect()->back()->withInput();
        }
    }

    public function update(
        Request $request,
        EmployeeUnitPosting $employee_unit_posting,
        OrgUnitRuleService $orgUnitRuleService
    ) {
        if (!$this->isPositionPromotionPosting($employee_unit_posting)) {
            abort(404);
        }

        $validated = $request->validate([
            'position_id' => 'required|exists:positions,id',
            'effective_date' => 'required|date',
            'document_reference' => 'nullable|string|max:191',
            'document_date' => 'nullable|date',
            'note' => 'nullable|string',
        ]);

        $employee = Employee::query()
            ->with('primaryUnitPosting')
            ->where('id', (int) $employee_unit_posting->employee_id)
            ->firstOrFail();
        $this->assertCanManageEmployee($employee, $orgUnitRuleService);

        $newPosition = Position::query()
            ->where('id', (int) $validated['position_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $oldMeta = $this->extractTaggedNoteValues((string) $employee_unit_posting->note, ['Old', 'New']);
        $relatedWorkHistory = $this->findPromotionWorkHistory($employee_unit_posting, $oldMeta);
        $effectiveDate = Carbon::parse($validated['effective_date'])->toDateString();
        $documentDate = !empty($validated['document_date'])
            ? Carbon::parse($validated['document_date'])->toDateString()
            : null;
        $normalizedNote = trim((string) ($validated['note'] ?? ''));

        DB::beginTransaction();
        try {
            $employee_unit_posting->position_id = (int) $newPosition->id;
            $employee_unit_posting->start_date = $effectiveDate;
            $employee_unit_posting->department_id = (int) ($employee_unit_posting->department_id ?: ($employee->sub_department_id ?: $employee->department_id));
            $employee_unit_posting->save();

            $this->rebuildEmployeePostingTimeline($employee);
            $employee->refresh();
            $employee_unit_posting->refresh();

            $oldPositionLabel = $this->previousPositionLabelForPosting($employee_unit_posting) ?: ($oldMeta['Old'] ?? '-');
            $newPositionLabel = $newPosition->position_name_km ?: $newPosition->position_name;

            $employee_unit_posting->note = $this->buildPositionPromotionNote(
                $oldPositionLabel,
                $newPositionLabel,
                $validated['document_reference'] ?? null,
                $documentDate,
                $normalizedNote
            );
            $employee_unit_posting->save();

            $this->syncPromotionWorkHistory(
                $employee,
                $employee_unit_posting,
                $relatedWorkHistory,
                $oldPositionLabel,
                $newPositionLabel,
                $validated['document_reference'] ?? null,
                $documentDate,
                $normalizedNote
            );

            $this->syncPostingServiceHistory(
                $employee_unit_posting,
                $employee->id,
                'position_change',
                'Position changed',
                "Changed position from {$oldPositionLabel} to {$newPositionLabel}",
                $effectiveDate,
                $oldPositionLabel,
                $newPositionLabel,
                [
                    'document_reference' => $validated['document_reference'] ?? null,
                    'document_date' => $documentDate,
                ]
            );

            $this->syncCurrentPositionProfileExtra($employee);

            DB::commit();
            Toastr::success('Position promotion updated successfully.', 'Success');

            return redirect()->route('employee-position-promotions.index', [
                'year' => Carbon::parse($effectiveDate)->year,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            activity()->causedBy(auth()->user())->log('An error occurred: ' . $e->getMessage());
            Toastr::error('Failed to update position promotion.', 'Error');
            return redirect()->back()->withInput();
        }
    }

    public function destroy(
        EmployeeUnitPosting $employee_unit_posting,
        OrgUnitRuleService $orgUnitRuleService
    ) {
        if (!$this->isPositionPromotionPosting($employee_unit_posting)) {
            abort(404);
        }

        $employee = Employee::query()
            ->with('primaryUnitPosting')
            ->where('id', (int) $employee_unit_posting->employee_id)
            ->firstOrFail();
        $this->assertCanManageEmployee($employee, $orgUnitRuleService);

        $meta = $this->extractTaggedNoteValues((string) $employee_unit_posting->note, ['Old', 'New']);
        $relatedWorkHistory = $this->findPromotionWorkHistory($employee_unit_posting, $meta);
        $fallbackPositionId = $this->resolvePositionIdByLabel($meta['Old'] ?? null);
        $year = optional($employee_unit_posting->start_date)->year ?: now()->year;

        DB::beginTransaction();
        try {
            if ($relatedWorkHistory) {
                $relatedWorkHistory->delete();
            }

            $this->deletePostingServiceHistory($employee_unit_posting);
            $employee_unit_posting->delete();

            $this->rebuildEmployeePostingTimeline($employee, $fallbackPositionId);
            $this->syncCurrentPositionProfileExtra($employee);

            DB::commit();
            Toastr::success('Position promotion deleted successfully.', 'Success');

            return redirect()->route('employee-position-promotions.index', [
                'year' => $year,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            activity()->causedBy(auth()->user())->log('An error occurred: ' . $e->getMessage());
            Toastr::error('Failed to delete position promotion.', 'Error');
            return redirect()->back();
        }
    }

    protected function currentPositionLabels($employees): array
    {
        $labels = [];
        foreach ($employees as $employee) {
            $labels[(int) $employee->id] = $employee->position?->position_name_km
                ?: ($employee->position?->position_name ?: '-');
        }
        return $labels;
    }

    protected function previousPositionLabelMap($promotions): array
    {
        $result = [];
        $employeeIds = collect($promotions)->pluck('employee_id')->map(function ($id) {
            return (int) $id;
        })->unique()->values()->all();

        if (empty($employeeIds)) {
            return $result;
        }

        $postingsByEmployee = EmployeeUnitPosting::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereNotNull('position_id')
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->groupBy('employee_id');

        foreach ($promotions as $promotion) {
            $rows = collect($postingsByEmployee->get((int) $promotion->employee_id, []))->values();
            $index = $rows->search(function ($row) use ($promotion) {
                return (int) $row->id === (int) $promotion->id;
            });

            if ($index === false || $index === 0) {
                $result[(int) $promotion->id] = '-';
                continue;
            }

            $previous = $rows->get($index - 1);
            $result[(int) $promotion->id] = $this->positionNameById((int) ($previous->position_id ?? 0)) ?: '-';
        }

        return $result;
    }

    protected function promotionDocumentMap($promotions, int $year): array
    {
        $result = [];
        $employeeIds = collect($promotions)->pluck('employee_id')->map(function ($id) {
            return (int) $id;
        })->unique()->values()->all();

        if (empty($employeeIds)) {
            return $result;
        }

        $workHistoryByEmployeeDate = EmployeeWorkHistory::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('work_status_name', 'ឡើងតួនាទី')
            ->whereYear('start_date', $year)
            ->orderByDesc('id')
            ->get()
            ->groupBy(function ($row) {
                return (int) $row->employee_id . '|' . (string) optional($row->start_date)->format('Y-m-d');
            });

        foreach ($promotions as $promotion) {
            $key = (int) $promotion->employee_id . '|' . (string) optional($promotion->start_date)->format('Y-m-d');
            $history = collect($workHistoryByEmployeeDate->get($key, []))->first();
            $result[(int) $promotion->id] = [
                'document_reference' => $history?->document_reference ?: null,
                'document_date' => optional($history?->document_date)->format('Y-m-d') ?: null,
            ];
        }

        return $result;
    }

    protected function positionNameById(?int $id): ?string
    {
        if (!$id) {
            return null;
        }

        $position = Position::withoutGlobalScopes()->find($id);
        return $position?->position_name_km ?: $position?->position_name;
    }

    protected function isPositionPromotionPosting(EmployeeUnitPosting $posting): bool
    {
        return str_starts_with(trim((string) $posting->note), '[POSITION_PROMOTION]');
    }

    protected function buildPositionPromotionNote(
        string $oldPositionLabel,
        string $newPositionLabel,
        ?string $documentReference,
        ?string $documentDate,
        ?string $note
    ): string {
        $parts = [
            '[POSITION_PROMOTION]',
            'Old: ' . (trim($oldPositionLabel) !== '' ? trim($oldPositionLabel) : '-'),
            'New: ' . (trim($newPositionLabel) !== '' ? trim($newPositionLabel) : '-'),
        ];

        $documentReference = trim((string) $documentReference);
        if ($documentReference !== '') {
            $parts[] = 'Doc: ' . $documentReference;
        }

        $documentDate = trim((string) $documentDate);
        if ($documentDate !== '') {
            $parts[] = 'DocDate: ' . $documentDate;
        }

        $note = trim((string) $note);
        if ($note !== '') {
            $parts[] = $note;
        }

        return implode(' | ', $parts);
    }

    protected function extractTaggedNoteValues(string $note, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = null;
        }

        $segments = array_map('trim', explode('|', $note));
        foreach ($segments as $segment) {
            foreach ($keys as $key) {
                $prefix = $key . ':';
                if (!str_starts_with($segment, $prefix)) {
                    continue;
                }

                $result[$key] = trim(substr($segment, strlen($prefix)));
            }
        }

        return $result;
    }

    protected function findPromotionWorkHistory(EmployeeUnitPosting $posting, array $meta = []): ?EmployeeWorkHistory
    {
        $query = EmployeeWorkHistory::query()
            ->where('employee_id', (int) $posting->employee_id)
            ->whereDate('start_date', optional($posting->start_date)->toDateString())
            ->where('work_status_name', 'ឡើងតួនាទី')
            ->orderByDesc('id');

        $oldLabel = trim((string) ($meta['Old'] ?? ''));
        $newLabel = trim((string) ($meta['New'] ?? ''));

        if ($oldLabel !== '') {
            $query->where('note', 'like', '%' . $oldLabel . '%');
        }
        if ($newLabel !== '') {
            $query->where('note', 'like', '%' . $newLabel . '%');
        }

        return $query->first();
    }

    protected function syncPromotionWorkHistory(
        Employee $employee,
        EmployeeUnitPosting $posting,
        ?EmployeeWorkHistory $history,
        string $oldPositionLabel,
        string $newPositionLabel,
        ?string $documentReference,
        ?string $documentDate,
        ?string $note
    ): void {
        $noteParts = [
            'តួនាទីចាស់: ' . ($oldPositionLabel !== '' ? $oldPositionLabel : '-'),
            'តួនាទីថ្មី: ' . ($newPositionLabel !== '' ? $newPositionLabel : '-'),
        ];

        $note = trim((string) $note);
        if ($note !== '') {
            $noteParts[] = $note;
        }

        if (!$history) {
            $history = new EmployeeWorkHistory();
            $history->employee_id = $employee->id;
            $history->work_status_name = 'ឡើងតួនាទី';
        }

        $history->start_date = optional($posting->start_date)->toDateString();
        $history->document_reference = trim((string) $documentReference) !== '' ? trim((string) $documentReference) : null;
        $history->document_date = trim((string) $documentDate) !== '' ? trim((string) $documentDate) : null;
        $history->note = implode(' | ', $noteParts);
        $history->save();
    }

    protected function syncPostingServiceHistory(
        EmployeeUnitPosting $posting,
        int $employeeId,
        string $eventType,
        string $title,
        string $details,
        string $eventDate,
        string $fromValue,
        string $toValue,
        array $metadata = []
    ): void {
        $history = EmployeeServiceHistory::query()
            ->where('reference_type', 'employee_unit_posting')
            ->where('reference_id', (int) $posting->id)
            ->latest('id')
            ->first();

        if (!$history) {
            app(EmployeeServiceHistoryService::class)->log(
                $employeeId,
                $eventType,
                $title,
                $details,
                $eventDate,
                $fromValue,
                $toValue,
                'employee_unit_posting',
                (int) $posting->id,
                $metadata
            );

            return;
        }

        $history->employee_id = $employeeId;
        $history->event_type = $eventType;
        $history->event_date = $eventDate;
        $history->title = $title;
        $history->details = $details;
        $history->from_value = $fromValue;
        $history->to_value = $toValue;
        $history->metadata = $metadata;
        $history->save();
    }

    protected function deletePostingServiceHistory(EmployeeUnitPosting $posting): void
    {
        EmployeeServiceHistory::query()
            ->where('reference_type', 'employee_unit_posting')
            ->where('reference_id', (int) $posting->id)
            ->delete();
    }

    protected function rebuildEmployeePostingTimeline(Employee $employee, ?int $fallbackPositionId = null): void
    {
        $postings = EmployeeUnitPosting::query()
            ->where('employee_id', (int) $employee->id)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->values();

        $lastPosting = null;

        foreach ($postings as $index => $posting) {
            $nextPosting = $postings->get($index + 1);
            $newEndDate = null;

            if ($nextPosting) {
                $candidateEndDate = Carbon::parse($nextPosting->start_date)->subDay()->toDateString();
                $startDate = optional($posting->start_date)->toDateString();
                $newEndDate = ($startDate && $candidateEndDate < $startDate)
                    ? $startDate
                    : $candidateEndDate;
            }

            $shouldBePrimary = $index === ($postings->count() - 1);
            $dirty = false;

            if ((bool) $posting->is_primary !== $shouldBePrimary) {
                $posting->is_primary = $shouldBePrimary;
                $dirty = true;
            }

            $normalizedCurrentEnd = optional($posting->end_date)->toDateString();
            if ($normalizedCurrentEnd !== $newEndDate) {
                $posting->end_date = $newEndDate;
                $dirty = true;
            }

            if ($dirty) {
                $posting->save();
            }

            if ($shouldBePrimary) {
                $lastPosting = $posting;
            }
        }

        $employee->refresh();

        if ($lastPosting && (int) ($lastPosting->position_id ?? 0) > 0) {
            $employee->position_id = (int) $lastPosting->position_id;
            $employee->save();
            return;
        }

        if ($fallbackPositionId && (int) $fallbackPositionId > 0) {
            $employee->position_id = (int) $fallbackPositionId;
            $employee->save();
        }
    }

    protected function previousPositionLabelForPosting(EmployeeUnitPosting $posting): ?string
    {
        $rows = EmployeeUnitPosting::query()
            ->where('employee_id', (int) $posting->employee_id)
            ->whereNotNull('position_id')
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->values();

        $index = $rows->search(fn ($row) => (int) $row->id === (int) $posting->id);
        if ($index === false || $index === 0) {
            return null;
        }

        $previous = $rows->get($index - 1);
        return $this->positionNameById((int) ($previous->position_id ?? 0));
    }

    protected function resolvePositionIdByLabel(?string $label): ?int
    {
        $label = trim((string) $label);
        if ($label === '' || $label === '-') {
            return null;
        }

        $position = Position::withoutGlobalScopes()
            ->where(function ($query) use ($label) {
                $query->where('position_name_km', $label)
                    ->orWhere('position_name', $label);
            })
            ->orderByDesc('id')
            ->first();

        return $position ? (int) $position->id : null;
    }

    protected function syncCurrentPositionProfileExtra(Employee $employee): void
    {
        $latestPromotion = EmployeeUnitPosting::query()
            ->where('employee_id', (int) $employee->id)
            ->where('note', 'like', '[POSITION_PROMOTION]%')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if (!$latestPromotion) {
            EmployeeProfileExtra::updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'current_position_start_date' => null,
                    'current_position_document_number' => null,
                    'current_position_document_date' => null,
                ]
            );
            return;
        }

        $workHistory = $this->findPromotionWorkHistory($latestPromotion, $this->extractTaggedNoteValues((string) $latestPromotion->note, ['Old', 'New']));

        EmployeeProfileExtra::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'current_position_start_date' => optional($latestPromotion->start_date)->toDateString(),
                'current_position_document_number' => $workHistory?->document_reference,
                'current_position_document_date' => optional($workHistory?->document_date)->toDateString(),
            ]
        );
    }

    protected function normalizeKhmerText(?string $text): string
    {
        $value = trim((string) $text);
        if ($value === '') {
            return '';
        }

        $looksMojibake = str_contains($value, 'á') || str_contains($value, 'â') || str_contains($value, 'Ã');
        if (!$looksMojibake) {
            return $value;
        }

        $iconv = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
        if (is_string($iconv) && $iconv !== '' && preg_match('/\p{Khmer}/u', $iconv)) {
            return trim($iconv);
        }

        $mb = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        if (is_string($mb) && $mb !== '' && preg_match('/\p{Khmer}/u', $mb)) {
            return trim($mb);
        }

        return $value;
    }

    protected function historyService(): EmployeeServiceHistoryService
    {
        return app(EmployeeServiceHistoryService::class);
    }

    protected function managedBranchIds(OrgUnitRuleService $orgUnitRuleService): ?array
    {
        if ($this->isSystemAdmin()) {
            return null;
        }

        $rootUnitId = $this->currentUserRootUnitId();
        if (!$rootUnitId) {
            return [];
        }

        return $orgUnitRuleService->branchIdsIncludingSelf($rootUnitId);
    }

    protected function applyManagedBranchScope($query, ?array $managedBranchIds): void
    {
        if (!is_array($managedBranchIds)) {
            return;
        }

        if (empty($managedBranchIds)) {
            $query->whereRaw('1=0');
            return;
        }

        $query->where(function ($q) use ($managedBranchIds) {
            $q->whereIn('department_id', $managedBranchIds)
                ->orWhereIn('sub_department_id', $managedBranchIds);
        });
    }

    protected function assertCanManageEmployee(Employee $employee, OrgUnitRuleService $orgUnitRuleService): void
    {
        $managedBranchIds = $this->managedBranchIds($orgUnitRuleService);
        if (!is_array($managedBranchIds)) {
            return;
        }

        $employeeUnitId = $this->employeeAssignedUnitId($employee);
        if (!$employeeUnitId || !in_array($employeeUnitId, $managedBranchIds, true)) {
            abort(403, 'អ្នកអាចគ្រប់គ្រងបានតែមន្ត្រីក្នុងអង្គភាពរបស់ខ្លួនប៉ុណ្ណោះ។');
        }
    }

    protected function isSystemAdmin(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return method_exists($user, 'admin')
            ? (bool) $user->admin()
            : (int) ($user->user_type_id ?? 0) === 1;
    }

    protected function currentUserRootUnitId(): ?int
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        $employee = $user->employee()->with('primaryUnitPosting')->first();
        if (!$employee) {
            return null;
        }

        $rootUnitId = $this->employeeAssignedUnitId($employee);
        if ($rootUnitId) {
            return $rootUnitId;
        }

        $postedUnitId = (int) optional($employee->primaryUnitPosting)->department_id;
        return $postedUnitId > 0 ? $postedUnitId : null;
    }

    protected function employeeAssignedUnitId(Employee $employee): ?int
    {
        $unitId = (int) ($employee->sub_department_id ?: $employee->department_id);
        if ($unitId > 0) {
            return $unitId;
        }

        $postedUnitId = (int) optional($employee->primaryUnitPosting)->department_id;
        return $postedUnitId > 0 ? $postedUnitId : null;
    }
}
